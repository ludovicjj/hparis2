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
    private const int MAX_FILE_SIZE_BYTES = 20 * 1024 * 1024;
    private const int MAX_IMAGE_DIMENSION = 8000;
    private const array ALLOWED_IMAGE_TYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG];

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

            $tempKey = PictureService::buildTempKey($picture);

            // 1 - Fetch and check file size via HEAD request before downloading
            $fileSize = $this->s3Service->getFileSize($tempKey);
            if ($fileSize === false) {
                throw new RuntimeException(sprintf(
                    'Temp object "%s" not found on S3 for picture #%d.',
                    $tempKey,
                    $pictureId
                ));
            }
            if ($fileSize > self::MAX_FILE_SIZE_BYTES) {
                throw new RuntimeException(sprintf(
                    'Temp object "%s" too large (%d bytes, max %d) for picture #%d.',
                    $tempKey,
                    $fileSize,
                    self::MAX_FILE_SIZE_BYTES,
                    $pictureId
                ));
            }

            // 2 - Pull the original binary back from S3
            $sourceContent = $this->s3Service->getFileContent($tempKey);
            if ($sourceContent === false) {
                throw new RuntimeException(sprintf(
                    'Failed to download temp object "%s" for picture #%d.',
                    $tempKey,
                    $pictureId
                ));
            }

            // 3 - Validate image type and dimensions before feeding GD. getimagesizefromstring
            //     only reads the header, so it's safe to call on the raw string.
            $imageInfo = @getimagesizefromstring($sourceContent);
            if ($imageInfo === false) {
                throw new RuntimeException(sprintf(
                    'Temp object "%s" is not a valid image for picture #%d.',
                    $tempKey,
                    $pictureId
                ));
            }
            [$width, $height, $imageType] = $imageInfo;
            if (!in_array($imageType, self::ALLOWED_IMAGE_TYPES, true)) {
                throw new RuntimeException(sprintf(
                    'Temp object "%s" has unsupported image type %d for picture #%d.',
                    $tempKey,
                    $imageType,
                    $pictureId
                ));
            }
            if ($width > self::MAX_IMAGE_DIMENSION || $height > self::MAX_IMAGE_DIMENSION) {
                throw new RuntimeException(sprintf(
                    'Temp object "%s" dimensions too large (%dx%d, max %d) for picture #%d.',
                    $tempKey,
                    $width,
                    $height,
                    self::MAX_IMAGE_DIMENSION,
                    $pictureId
                ));
            }

            // 4 - Resize in memory: 1200px lightbox + 400px thumbnail
            $optimized = $this->imageOptimizerService->optimizePicture($sourceContent);

            // 5 - Compute the final S3 keys for this picture
            $baseFilename = $picture->getFilename();
            $baseName = pathinfo($baseFilename, PATHINFO_FILENAME);
            $galleryId = $picture->getGallery()->getId();

            $lightboxKey = sprintf('galleries/%d/%s.jpg', $galleryId, $baseName);
            $thumbnailKey = sprintf('galleries/%d/%s-thumb.jpg', $galleryId, $baseName);

            // 6 - Upload both variants as public-readable JPEGs
            if (!$this->s3Service->uploadPublicFile($lightboxKey, $optimized['lightbox'], 'image/jpeg')) {
                throw new RuntimeException(sprintf('Failed to upload lightbox to S3 (%s).', $lightboxKey));
            }
            if (!$this->s3Service->uploadPublicFile($thumbnailKey, $optimized['thumbnail'], 'image/jpeg')) {
                throw new RuntimeException(sprintf('Failed to upload thumbnail to S3 (%s).', $thumbnailKey));
            }

            // 7 - Persist the new state
            $picture->setLightboxPath($lightboxKey);
            $picture->setThumbnailPath($thumbnailKey);
            $picture->setStatus(Picture::STATUS_READY);
            $this->entityManager->flush();

            // 8 - Cleanup the temp object
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
