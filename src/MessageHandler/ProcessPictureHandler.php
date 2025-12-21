<?php

namespace App\MessageHandler;

use App\Entity\Picture;
use App\Message\ProcessPictureMessage;
use App\Repository\PictureRepository;
use App\Service\ImageOptimizerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class ProcessPictureHandler
{
    public function __construct(
        private PictureRepository      $pictureRepository,
        private ImageOptimizerService  $imageOptimizerService,
        private EntityManagerInterface $entityManager,
        private Filesystem             $filesystem,
        private string                 $uploadDirectory,
    ) {}

    public function __invoke(ProcessPictureMessage $message): void
    {
        // 1. Récupérer la Picture
        $picture = $this->pictureRepository->find($message->getPictureId());

        if (!$picture) {
            return;
        }

        // 2. Lire tempPath et préparer la destination
        $tempPath = $this->uploadDirectory . '/' . ltrim($picture->getTempPath(), '/');
        $datePath = (new \DateTimeImmutable())->format('Y/m/d');
        $picturesDir = $this->uploadDirectory . '/pictures/' . $datePath;
        $this->filesystem->mkdir($picturesDir);

        // 3. Resize picture (1200px lightbox + 400px thumbnail)
        $optimized = $this->imageOptimizerService->optimizePicture(
            $tempPath,
            $picturesDir,
            $picture->getFilename()
        );

        // 4. Mettre à jour la Picture
        $picture->setLightboxPath('/uploads/pictures/' . $datePath . '/' . $optimized['lightbox']);
        $picture->setThumbnailPath('/uploads/pictures/' . $datePath . '/' . $optimized['thumbnail']);
        $picture->setTempPath(null);
        $picture->setStatus(Picture::STATUS_READY);

        // 5. Supprimer le fichier temporaire
        $this->filesystem->remove($tempPath);

        // 6. Persister en BDD
        $this->entityManager->flush();
    }
}
