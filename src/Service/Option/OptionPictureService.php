<?php

namespace App\Service\Option;

use App\Entity\Option;
use App\Entity\OptionPicture;
use App\Repository\OptionPictureRepository;
use App\Service\ImageOptimizerService;
use App\Service\S3Service;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;

readonly class OptionPictureService
{
    public const int MAX_PICTURES_PER_OPTION = 5;

    private const string S3_PREFIX = 'option_pictures';
    private const array ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png'];
    private const int MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB

    public function __construct(
        private OptionPictureRepository $repository,
        private EntityManagerInterface $entityManager,
        private ImageOptimizerService $imageOptimizer,
        private S3Service $s3Service,
        private Security $security,
    ) {
    }

    /**
     * Synchronous upload: validate file, generate the two resized variants
     * (1200px + 400px), upload both to S3, persist the OptionPicture row.
     * Rolls back the lightbox object if the thumbnail upload fails.
     */
    public function upload(Option $option, UploadedFile $file): OptionPicture
    {
        if ($option->getId() === null) {
            throw new RuntimeException('Option must be persisted before uploading pictures.');
        }

        if ($this->repository->countByOption($option) >= self::MAX_PICTURES_PER_OPTION) {
            throw new RuntimeException(sprintf('Limite atteinte : %d images max.', self::MAX_PICTURES_PER_OPTION));
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

        $base = sprintf('%s/%d/%s', self::S3_PREFIX, $option->getId(), uniqid());
        $lightboxKey = $base . '.jpg';
        $thumbnailKey = $base . '-thumb.jpg';

        if (!$this->s3Service->uploadPublicFile($lightboxKey, $variants['lightbox'], 'image/jpeg')) {
            throw new RuntimeException('Échec de l\'upload S3 (lightbox).');
        }

        if (!$this->s3Service->uploadPublicFile($thumbnailKey, $variants['thumbnail'], 'image/jpeg')) {
            $this->s3Service->deleteFile($lightboxKey);
            throw new RuntimeException('Échec de l\'upload S3 (thumbnail).');
        }

        $picture = new OptionPicture()
            ->setOption($option)
            ->setLightboxPath($lightboxKey)
            ->setThumbnailPath($thumbnailKey)
            ->setPosition($this->repository->getNextPositionByOption($option))
            ->setCreatedBy($this->security->getUser());

        $this->entityManager->persist($picture);
        $this->entityManager->flush();

        return $picture;
    }

    /**
     * Delete an OptionPicture entity and both associated S3 objects.
     */
    public function delete(OptionPicture $picture): void
    {
        $this->s3Service->deleteFile($picture->getLightboxPath());
        $this->s3Service->deleteFile($picture->getThumbnailPath());

        $this->entityManager->remove($picture);
        $this->entityManager->flush();
    }

    /**
     * Delete every S3 file (lightbox + thumbnail) attached to an option's pictures.
     * Does NOT remove the OptionPicture rows — caller relies on the FK CASCADE to
     * clean those up when the Option row is removed.
     */
    public function cleanupFilesForOption(Option $option): void
    {
        foreach ($this->repository->findByOptionOrdered($option) as $picture) {
            $this->s3Service->deleteFile($picture->getLightboxPath());
            $this->s3Service->deleteFile($picture->getThumbnailPath());
        }
    }

    /**
     * Reorder OptionPicture rows of an option to match the given id sequence.
     * Ids that don't belong to the option are silently ignored.
     *
     * @param int[] $ids
     */
    public function reorder(Option $option, array $ids): void
    {
        $pictures = $this->repository->findBy(['id' => $ids, 'option' => $option]);

        $indexed = [];
        foreach ($pictures as $picture) {
            $indexed[$picture->getId()] = $picture;
        }

        foreach ($ids as $position => $id) {
            if (isset($indexed[$id])) {
                $indexed[$id]->setPosition($position);
            }
        }

        $this->entityManager->flush();
    }
}
