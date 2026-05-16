<?php

namespace App\Service\Video;

use App\Entity\Video;
use App\Entity\VideoPicture;
use App\Repository\VideoPictureRepository;
use App\Service\ImageOptimizerService;
use App\Service\S3Service;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;

readonly class VideoPictureService
{
    public const int MAX_PICTURES_PER_VIDEO = 5;

    private const array ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png'];
    private const int MAX_FILE_SIZE = 5 * 1024 * 1024;  // 5 MB

    public function __construct(
        private VideoPictureRepository $repository,
        private EntityManagerInterface $entityManager,
        private ImageOptimizerService $imageOptimizer,
        private S3Service $s3Service,
        private Security $security,
    ) {
    }

    /**
     * Synchronous upload : validate file, generate the two resized variants
     * (1200px + 400px), upload both to S3, persist the VideoPicture row.
     *
     * Throws on any error. If the upload partially succeeded (lightbox uploaded
     * but thumbnail failed), the lightbox S3 object is cleaned up before throwing.
     */
    public function upload(Video $video, UploadedFile $file): VideoPicture
    {
        if ($video->getId() === null) {
            throw new RuntimeException('Video must be persisted before uploading pictures.');
        }

        if ($this->repository->countByVideo($video) >= self::MAX_PICTURES_PER_VIDEO) {
            throw new RuntimeException(sprintf('Limite atteinte : %d images max par vidéo.', self::MAX_PICTURES_PER_VIDEO));
        }

        if (!in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES, true)) {
            throw new InvalidArgumentException('Type de fichier non autorisé. Formats acceptés : JPG, PNG.');
        }

        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new InvalidArgumentException(sprintf('Fichier trop volumineux. Taille max : %d Mo.', self::MAX_FILE_SIZE / 1024 / 1024));
        }

        $sourceContent = file_get_contents($file->getPathname());
        if ($sourceContent === false) {
            throw new RuntimeException('Impossible de lire le fichier uploadé.');
        }

        $variants = $this->imageOptimizer->optimizePicture($sourceContent);

        $base = sprintf('video_pictures/%d/%s', $video->getId(), uniqid());
        $lightboxKey = $base . '.jpg';
        $thumbnailKey = $base . '-thumb.jpg';

        if (!$this->s3Service->uploadPublicFile($lightboxKey, $variants['lightbox'], 'image/jpeg')) {
            throw new RuntimeException('Échec de l\'upload S3 (lightbox).');
        }

        if (!$this->s3Service->uploadPublicFile($thumbnailKey, $variants['thumbnail'], 'image/jpeg')) {
            $this->s3Service->deleteFile($lightboxKey);
            throw new RuntimeException('Échec de l\'upload S3 (thumbnail).');
        }

        $videoPicture = new VideoPicture()
            ->setVideo($video)
            ->setLightboxPath($lightboxKey)
            ->setThumbnailPath($thumbnailKey)
            ->setPosition($this->repository->getNextPositionByVideo($video))
            ->setCreatedBy($this->security->getUser());

        $this->entityManager->persist($videoPicture);
        $this->entityManager->flush();

        return $videoPicture;
    }

    /**
     * Delete a VideoPicture entity and both associated S3 objects.
     */
    public function delete(VideoPicture $videoPicture): void
    {
        $this->s3Service->deleteFile($videoPicture->getLightboxPath());
        $this->s3Service->deleteFile($videoPicture->getThumbnailPath());

        $this->entityManager->remove($videoPicture);
        $this->entityManager->flush();
    }

    /**
     * Delete every S3 file (lightbox + thumbnail) attached to a video's pictures.
     * Does NOT remove the VideoPicture rows — caller relies on the FK CASCADE
     * to clean those up when the Video row is removed.
     */
    public function cleanupFilesForVideo(Video $video): void
    {
        $pictures = $this->repository->findByVideoOrdered($video);

        foreach ($pictures as $picture) {
            $this->s3Service->deleteFile($picture->getLightboxPath());
            $this->s3Service->deleteFile($picture->getThumbnailPath());
        }
    }

    /**
     * Reorder the VideoPicture rows of a video to match the given id sequence.
     * Ids that don't belong to the video are silently ignored.
     */
    public function reorder(Video $video, array $ids): void
    {
        $pictures = $this->repository->findBy(['id' => $ids, 'video' => $video]);
        $indexed = [];
        foreach ($pictures as $vp) {
            $indexed[$vp->getId()] = $vp;
        }

        foreach ($ids as $position => $id) {
            if (isset($indexed[$id])) {
                $indexed[$id]->setPosition($position);
            }
        }

        $this->entityManager->flush();
    }
}
