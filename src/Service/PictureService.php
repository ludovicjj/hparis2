<?php

namespace App\Service;

use App\Entity\Gallery;
use App\Entity\Picture;
use App\Entity\User;
use App\Repository\PictureRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
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
     * Attach pending pictures to a gallery from form submission
     */
    public function handle(FormInterface $form): void
    {
        /** @var ?Request $request */
        $request = $this->requestStack->getCurrentRequest();

        /** @var Gallery $gallery */
        $gallery = $form->getData();

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
            $gallery->addPicture($picture);
            $picture->setStatus(Picture::STATUS_ATTACHED);
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
     * Upload a file and create a Picture entity
     */
    public function upload(UploadedFile $file): Picture
    {
        // Extract file info BEFORE processing
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = strtolower($file->guessExtension() ?? $file->getClientOriginalExtension());
        $filename = $this->generateUniqueFilename($originalName, $extension);
        $datePath = $this->getDatePath();

        // Create Picture DIR
        $picturesDir = $this->uploadDirectory . '/pictures/' . $datePath;
        $this->filesystem->mkdir($picturesDir);

        // Create and Upload : lightbox (1200px) + thumbnail (400px)
        $optimized = $this->imageOptimizerService->optimizePicture(
            $file->getPathname(),
            $picturesDir,
            $filename
        );

        // Build path for picture
        $relativePath = '/uploads/pictures/' . $datePath . '/' . $optimized['lightbox'];
        $thumbnailRelativePath = '/uploads/pictures/' . $datePath . '/' . $optimized['thumbnail'];

        $picture = $this->createPicture($originalName, $extension, $filename, $relativePath, $thumbnailRelativePath);

        $this->entityManager->persist($picture);
        $this->entityManager->flush();

        return $picture;
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

    public function deleteOrphanPicturesByUser(User $user): int
    {
        $pictures = $this->pictureRepository->findUnattachedByUser($user);

        foreach ($pictures as $picture) {
            $this->deleteFile($picture);
            $this->entityManager->remove($picture);
        }

        $this->entityManager->flush();

        return count($pictures);
    }

    /**
     * Delete only the files (not the entity)
     */
    public function deleteFile(Picture $picture): void
    {
        // Delete lightbox image
        $absolutePath = $this->getAbsolutePath($picture->getPath());
        if ($this->filesystem->exists($absolutePath)) {
            $this->filesystem->remove($absolutePath);
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

    private function createPicture(string $originalName, string $extension, string $filename, string $relativePath, string $thumbnailPath): Picture
    {
        return new Picture()
            ->setFilename($filename)
            ->setOriginalName($originalName)
            ->setPath($relativePath)
            ->setThumbnailPath($thumbnailPath)
            ->setType($extension)
            ->setStatus(Picture::STATUS_PENDING)
            ->setCreatedBy($this->security->getUser());
    }

    private function getAbsolutePath(string $relativePath): string
    {
        $relativePath = ltrim($relativePath, '/');

        return dirname($this->uploadDirectory) . '/' . $relativePath;
    }
}