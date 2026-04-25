<?php

namespace App\Service;

use App\Entity\Gallery;
use App\Entity\Picture;
use App\Repository\PictureRepository;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\String\Slugger\SluggerInterface;

readonly class PictureService
{
    private const array ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png'];

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
     * Prepare a direct-to-S3 upload:
     *  - validates filename and content-type (no binary involved here)
     *  - persists a Picture entity (status=processing) to obtain its id
     *  - generates a short-lived presigned PUT URL pinned to the negotiated content-type
     *
     * The browser then PUTs the binary directly to S3 and finally calls
     * PictureController::confirmUploaded() to dispatch ProcessPictureMessage.
     *
     * @return array{picture: Picture, uploadUrl: string}
     */
    public function prepareUpload(string $originalFilename, string $contentType, Gallery $gallery): array
    {
        if (trim($originalFilename) === '') {
            throw new InvalidArgumentException('Nom de fichier manquant');
        }

        if (!in_array($contentType, self::ALLOWED_MIME_TYPES, true)) {
            throw new InvalidArgumentException('Type de fichier non autorisé');
        }

        $originalName = pathinfo($originalFilename, PATHINFO_FILENAME);
        $filename = $this->generateUniqueFilename($originalName);

        $position = $this->pictureRepository->findMaxPositionByGallery($gallery) + 1;

        $picture = new Picture();
        $picture->setFilename($filename);
        $picture->setOriginalName($originalName);
        $picture->setType('jpg');
        $picture->setGallery($gallery);
        $picture->setCreatedBy($this->security->getUser());
        $picture->setPosition($position);

        // Persist first so we get an id (needed for the temp S3 key).
        $this->entityManager->persist($picture);
        $this->entityManager->flush();

        $tempKey = self::buildTempKey($picture);
        $uploadUrl = $this->s3Service->createPresignedPutUrl($tempKey, $contentType);

        return [
            'picture' => $picture,
            'uploadUrl' => $uploadUrl,
        ];
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
        if (in_array($picture->getStatus(), [Picture::STATUS_PROCESSING, Picture::STATUS_FAILED])) {
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
}
