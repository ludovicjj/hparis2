<?php

namespace App\Service;

use App\Entity\Picture;
use App\Repository\PictureRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\String\Slugger\SluggerInterface;

readonly class PictureService
{
    private const array ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png'];
    private const int MAX_FILE_SIZE = 20 * 1024 * 1024; // 20MB

    public function __construct(
        private RequestStack $requestStack,
        private PictureRepository $pictureRepository,
        private SluggerInterface $slugger,
        private Filesystem $filesystem,
        private EntityManagerInterface $entityManager,
        private ImageOptimizerService $imageOptimizerService,
        private Security $security,
        private string $uploadDirectory,
    ) {
    }

    /**
     * Sort gallery's pictures
     */
    public function sortPicture(): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            return;
        }

        $pictureIds = $request->request->get('picture_ids', '');

        if (empty($pictureIds)) {
            return;
        }

        // Filters Ids:
        // - convert to array
        // - transform array value : string to integer
        // - filter array value : exclude falsy value
        $ids = array_filter(array_map('intval', explode(',', $pictureIds)));
        $pictures = $this->pictureRepository->findOrderedByIds($ids);

        foreach ($pictures as $position => $picture) {
            $picture->setPosition($position);
        }
    }

    /**
     * Validate an uploaded file
     */
    public function validate(?UploadedFile $file): ?string
    {
        if (!$file) {
            return 'Aucun fichier reçu';
        }

        if (!$file->isValid()) {
            return $file->getErrorMessage();
        }

        if (!in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES)) {
            return 'Type de fichier non autorisé';
        }

        if ($file->getSize() > self::MAX_FILE_SIZE) {
            return 'Fichier trop volumineux (max 20 Mo)';
        }

        return null;
    }

    /**
     * Delete a picture entity and its file
     */
    public function delete(Picture $picture): void
    {
        // delete file
        $this->deleteFile($picture);

        // delete entity
        $this->entityManager->remove($picture);
        $this->entityManager->flush();
    }

    /**
     * Delete only the files (not the entity)
     */
    public function deleteFile(Picture $picture): void
    {
        // Delete temp file (if still processing)
        $tempPath = $picture->getTempPath();
        if ($tempPath) {
            $absoluteTempPath = $this->uploadDirectory . '/' . ltrim($tempPath, '/');
            if ($this->filesystem->exists($absoluteTempPath)) {
                $this->filesystem->remove($absoluteTempPath);
            }
        }

        // Delete lightbox image
        $lightboxPath = $picture->getLightboxPath();
        if ($lightboxPath) {
            $absoluteLightboxPath = $this->getAbsolutePath($lightboxPath);
            if ($this->filesystem->exists($absoluteLightboxPath)) {
                $this->filesystem->remove($absoluteLightboxPath);
            }
        }

        // Delete thumbnail image
        $thumbnailPath = $picture->getThumbnailPath();
        if ($thumbnailPath) {
            $absoluteThumbnailPath = $this->getAbsolutePath($thumbnailPath);
            if ($this->filesystem->exists($absoluteThumbnailPath)) {
                $this->filesystem->remove($absoluteThumbnailPath);
            }
        }
    }

    private function generateUniqueFilename(string $originalName, string $extension): string
    {
        $safeFilename = $this->slugger->slug($originalName);

        return $safeFilename . '-' . uniqid() . '.' . $extension;
    }

    private function getDatePath(): string
    {
        return new DateTimeImmutable()->format('Y/m/d');
    }

    private function moveFile(UploadedFile $file, string $datePath, string $filename): void
    {
        $picturesDir = $this->uploadDirectory . '/pictures/' . $datePath;
        $this->filesystem->mkdir($picturesDir);
        $file->move($picturesDir, $filename);
    }

    private function getAbsolutePath(string $relativePath): string
    {
        $relativePath = ltrim($relativePath, '/');

        return dirname($this->uploadDirectory) . '/' . $relativePath;
    }
}