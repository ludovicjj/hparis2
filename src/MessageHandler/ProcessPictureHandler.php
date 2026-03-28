<?php

namespace App\MessageHandler;

use App\Entity\Picture;
use App\Message\ProcessPictureMessage;
use App\Repository\PictureRepository;
use App\Service\ImageOptimizerService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler]
readonly class ProcessPictureHandler
{
    public function __construct(
        private PictureRepository      $pictureRepository,
        private ImageOptimizerService  $imageOptimizerService,
        private EntityManagerInterface $entityManager,
        private Filesystem             $filesystem,
        private string                 $uploadDirectory,
        private LoggerInterface        $logger,
    ) {}

    public function __invoke(ProcessPictureMessage $message): void
    {
        try {
            // 1. Fetch Picture
            $picture = $this->pictureRepository->find($message->getPictureId());

            if (!$picture) {
                return;
            }

            // 2. Fetch tempPath and prepare target dir
            $tempPath = $this->uploadDirectory . '/' . ltrim($picture->getTempPath(), '/');
            $datePath = new DateTimeImmutable()->format('Y/m/d');
            $picturesDir = $this->uploadDirectory . '/pictures/' . $datePath;
            $this->filesystem->mkdir($picturesDir);

            // 3. Resize picture (1200px lightbox + 400px thumbnail)
            $optimized = $this->imageOptimizerService->optimizePicture(
                $tempPath,
                $picturesDir,
                $picture->getFilename()
            );

            // 4. Update Picture with Thumbnail and Lightbox format
            $picture->setLightboxPath('/uploads/pictures/' . $datePath . '/' . $optimized['lightbox']);
            $picture->setThumbnailPath('/uploads/pictures/' . $datePath . '/' . $optimized['thumbnail']);
            $picture->setTempPath(null);
            $picture->setStatus(Picture::STATUS_READY);

            // 5. Remove Temp file
            $this->filesystem->remove($tempPath);

            // 6. BDD
            $this->entityManager->flush();
        } catch (Throwable $exception) {
            $this->logger->error('Failed to process picture #{id}: {message}', [
                'id' => $message->getPictureId(),
                'message' => $exception->getMessage(),
            ]);

            $picture = $this->pictureRepository->find($message->getPictureId());

            if ($picture) {
                $picture->setStatus(Picture::STATUS_FAILED);
                $this->entityManager->flush();
            }
        }
    }
}
