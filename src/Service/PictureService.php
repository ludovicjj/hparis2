<?php

namespace App\Service;

use App\Entity\Gallery;
use App\Entity\Picture;
use App\Repository\PictureRepository;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;
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
        private EntityManagerInterface $entityManager,
        private Security $security,
        private S3Service $s3Service,
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
     * Upload a new picture for a gallery:
     *  - validates the file
     *  - persists a Picture entity (status=processing)
     *  - uploads the original binary to S3 under temp/{pictureId}.jpg
     *  - returns the Picture so the controller can dispatch the async resize message
     *
     * Final lightbox + thumbnail variants are produced asynchronously by ProcessPictureHandler.
     */
    public function createFromUpload(?UploadedFile $file, Gallery $gallery): Picture
    {
        // 1 - Validate
        $error = $this->validate($file);
        if ($error) {
            throw new InvalidArgumentException($error);
        }

        // 2 - Generate the canonical filename used for the final S3 keys.
        //     Always .jpg because the worker re-encodes everything to JPEG.
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $filename = $this->generateUniqueFilename($originalName);

        // 3 - Read the upload binary into memory once. Browser already compressed it
        //     to ~1 MB so this is cheap.
        $content = $file->getContent();

        // 4 - Persist the Picture first so we get an id (needed for the temp S3 key).
        $position = $this->pictureRepository->findMaxPositionByGallery($gallery) + 1;

        $picture = new Picture();
        $picture->setFilename($filename);
        $picture->setOriginalName($originalName);
        $picture->setType('jpg');
        $picture->setGallery($gallery);
        $picture->setCreatedBy($this->security->getUser());
        $picture->setPosition($position);

        $this->entityManager->persist($picture);
        $this->entityManager->flush();

        // 5 - Upload the original binary to S3 under the deterministic temp key.
        //     If this fails, roll back the Picture entity so we don't leave an orphan.
        $tempKey = $this->buildTempKey($picture);
        if (!$this->s3Service->uploadPrivateFile($tempKey, $content, $file->getMimeType())) {
            $this->entityManager->remove($picture);
            $this->entityManager->flush();
            throw new RuntimeException('Failed to upload picture to S3.');
        }

        return $picture;
    }

    /**
     * Delete a picture entity and all associated S3 objects.
     */
    public function delete(Picture $picture): void
    {
        $this->deleteFile($picture);

        $this->entityManager->remove($picture);
        $this->entityManager->flush();
    }

    /**
     * Delete every S3 object associated with a picture (temp + lightbox + thumbnail).
     * Does not touch the Picture entity. Safe to call on a partially-processed picture.
     */
    public function deleteFile(Picture $picture): void
    {
        // Temp file: only present while the picture is still processing.
        if ($picture->getStatus() === Picture::STATUS_PROCESSING) {
            $tempKey = $this->buildTempKey($picture);
            $this->s3Service->deleteFile($tempKey);
        }

        $lightboxKey = $picture->getLightboxPath();
        if ($lightboxKey) {
            $this->s3Service->deleteFile($lightboxKey);
        }

        $thumbnailKey = $picture->getThumbnailPath();
        if ($thumbnailKey) {
            $this->s3Service->deleteFile($thumbnailKey);
        }
    }

    /**
     * Build the deterministic S3 key used to store the temporary original of a picture
     * between upload and async processing. Shared by PictureService and ProcessPictureHandler.
     */
    public static function buildTempKey(Picture $picture): string
    {
        return 'temp/' . $picture->getId() . '.jpg';
    }

    private function generateUniqueFilename(string $originalName): string
    {
        $safeFilename = $this->slugger->slug($originalName)->slice(0, 100);

        return $safeFilename . '-' . uniqid() . '.jpg';
    }

    /**
     * Validate an uploaded file
     */
    private function validate(?UploadedFile $file): ?string
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
}
