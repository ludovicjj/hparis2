<?php

namespace App\MessageHandler;

use App\Entity\Picture;
use App\Message\ProcessPictureMessage;
use App\Repository\PictureRepository;
use App\Service\ImageOptimizerService;
use App\Service\PictureService;
use App\Service\S3Service;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler]
readonly class ProcessPictureHandler
{
    public function __construct(
        private PictureRepository      $pictureRepository,
        private ImageOptimizerService  $imageOptimizerService,
        private EntityManagerInterface $entityManager,
        private S3Service              $s3Service,
        private LoggerInterface        $logger,
    ) {}

    public function __invoke(ProcessPictureMessage $message): void
    {
        $pictureId = $message->getPictureId();

        try {
            $picture = $this->pictureRepository->find($pictureId);

            // Picture was deleted between dispatch and processing — no-op.
            if (!$picture) {
                return;
            }

            // 1 - Pull the original binary back from S3
            $tempKey = PictureService::buildTempKey($picture);
            $sourceContent = $this->s3Service->getFileContent($tempKey);

            if ($sourceContent === false) {
                throw new RuntimeException(sprintf(
                    'Temp object "%s" not found on S3 for picture #%d.',
                    $tempKey,
                    $pictureId
                ));
            }

            // 2 - Resize in memory: 1200px lightbox + 400px thumbnail
            $optimized = $this->imageOptimizerService->optimizePicture($sourceContent);

            // 3 - Compute the final S3 keys for this picture
            $baseFilename = $picture->getFilename();
            $baseName = pathinfo($baseFilename, PATHINFO_FILENAME);
            $galleryId = $picture->getGallery()->getId();

            $lightboxKey = sprintf('galleries/%d/%s.jpg', $galleryId, $baseName);
            $thumbnailKey = sprintf('galleries/%d/%s-thumb.jpg', $galleryId, $baseName);

            // 4 - Upload both variants as public-readable JPEGs
            if (!$this->s3Service->uploadPublicFile($lightboxKey, $optimized['lightbox'], 'image/jpeg')) {
                throw new RuntimeException(sprintf('Failed to upload lightbox to S3 (%s).', $lightboxKey));
            }
            if (!$this->s3Service->uploadPublicFile($thumbnailKey, $optimized['thumbnail'], 'image/jpeg')) {
                throw new RuntimeException(sprintf('Failed to upload thumbnail to S3 (%s).', $thumbnailKey));
            }

            // 5 - Persist the new state
            $picture->setLightboxPath($lightboxKey);
            $picture->setThumbnailPath($thumbnailKey);
            $picture->setStatus(Picture::STATUS_READY);
            $this->entityManager->flush();

            // 6 - Cleanup the temp object
            $this->s3Service->deleteFile($tempKey);
        } catch (Throwable $exception) {
            $this->logger->error('Failed to process picture #{id}: {message}', [
                'id' => $pictureId,
                'message' => $exception->getMessage(),
            ]);

            $picture = $this->pictureRepository->find($pictureId);
            if ($picture) {
                $picture->setStatus(Picture::STATUS_FAILED);
                $this->entityManager->flush();
            }
        }
    }
}
