<?php

namespace App\Service;

use App\Entity\Gallery;
use App\Entity\Picture;
use App\Repository\PictureRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
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
        // Extract file info BEFORE moving (temp file will be deleted after move)
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = strtolower($file->guessExtension() ?? $file->getClientOriginalExtension());
        $filename = $this->generateFilename($originalName, $extension);
        $datePath = $this->getDatePath();
        $relativePath = '/uploads/pictures/' . $datePath . '/' . $filename;

        $this->moveFile($file, $datePath, $filename);

        $picture = $this->createPicture($originalName, $extension, $filename, $relativePath);

        $this->entityManager->persist($picture);
        $this->entityManager->flush();

        return $picture;
    }

    /**
     * Delete a picture and its file
     */
    public function delete(Picture $picture): void
    {
        $this->deleteFile($picture);

        $this->entityManager->remove($picture);
        $this->entityManager->flush();
    }

    /**
     * Delete only the file (not the entity)
     */
    public function deleteFile(Picture $picture): void
    {
        $absolutePath = $this->getAbsolutePath($picture);

        if ($this->filesystem->exists($absolutePath)) {
            $this->filesystem->remove($absolutePath);
        }
    }

    private function generateFilename(string $originalName, string $extension): string
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

    private function createPicture(string $originalName, string $extension, string $filename, string $relativePath): Picture
    {
        $picture = new Picture();
        $picture->setFilename($filename);
        $picture->setOriginalName($originalName);
        $picture->setPath($relativePath);
        $picture->setType($extension);
        $picture->setStatus(Picture::STATUS_PENDING);

        return $picture;
    }

    private function getAbsolutePath(Picture $picture): string
    {
        $relativePath = ltrim($picture->getPath(), '/');

        return dirname($this->uploadDirectory) . '/' . $relativePath;
    }
}