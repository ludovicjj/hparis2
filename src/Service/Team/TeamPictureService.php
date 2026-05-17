<?php

namespace App\Service\Team;

use App\Entity\Team;
use App\Entity\TeamPicture;
use App\Repository\TeamPictureRepository;
use App\Service\ImageOptimizerService;
use App\Service\S3Service;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;

readonly class TeamPictureService
{
    public const int MAX_PICTURES_PER_TEAM = 5;

    private const string S3_PREFIX = 'team_pictures';
    private const array ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png'];
    private const int MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB

    public function __construct(
        private TeamPictureRepository $repository,
        private EntityManagerInterface $entityManager,
        private ImageOptimizerService $imageOptimizer,
        private S3Service $s3Service,
        private Security $security,
    ) {
    }

    /**
     * Synchronous upload: validate file, generate the two resized variants
     * (1200px + 400px), upload both to S3, persist the TeamPicture row.
     * Rolls back the lightbox object if the thumbnail upload fails.
     */
    public function upload(Team $team, UploadedFile $file): TeamPicture
    {
        if ($team->getId() === null) {
            throw new RuntimeException('Team must be persisted before uploading pictures.');
        }

        if ($this->repository->countByTeam($team) >= self::MAX_PICTURES_PER_TEAM) {
            throw new RuntimeException(sprintf('Limite atteinte : %d images max.', self::MAX_PICTURES_PER_TEAM));
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

        $base = sprintf('%s/%d/%s', self::S3_PREFIX, $team->getId(), uniqid());
        $lightboxKey = $base . '.jpg';
        $thumbnailKey = $base . '-thumb.jpg';

        if (!$this->s3Service->uploadPublicFile($lightboxKey, $variants['lightbox'], 'image/jpeg')) {
            throw new RuntimeException('Échec de l\'upload S3 (lightbox).');
        }

        if (!$this->s3Service->uploadPublicFile($thumbnailKey, $variants['thumbnail'], 'image/jpeg')) {
            $this->s3Service->deleteFile($lightboxKey);
            throw new RuntimeException('Échec de l\'upload S3 (thumbnail).');
        }

        $picture = new TeamPicture()
            ->setTeam($team)
            ->setLightboxPath($lightboxKey)
            ->setThumbnailPath($thumbnailKey)
            ->setPosition($this->repository->getNextPositionByTeam($team))
            ->setCreatedBy($this->security->getUser());

        if ($team->isDraft()) {
            $team->setIsDraft(false);
        }

        $this->entityManager->persist($picture);
        $this->entityManager->flush();

        return $picture;
    }

    /**
     * Delete a TeamPicture entity and both associated S3 objects.
     */
    public function delete(TeamPicture $picture): void
    {
        $this->s3Service->deleteFile($picture->getLightboxPath());
        $this->s3Service->deleteFile($picture->getThumbnailPath());

        $this->entityManager->remove($picture);
        $this->entityManager->flush();
    }

    /**
     * Delete every S3 file (lightbox + thumbnail) attached to a team's pictures.
     * Does NOT remove the TeamPicture rows — caller relies on the FK CASCADE to
     * clean those up when the Team row is removed.
     */
    public function cleanupFilesForTeam(Team $team): void
    {
        foreach ($this->repository->findByTeamOrdered($team) as $picture) {
            $this->s3Service->deleteFile($picture->getLightboxPath());
            $this->s3Service->deleteFile($picture->getThumbnailPath());
        }
    }

    /**
     * Reorder TeamPicture rows of a team to match the given id sequence.
     * Ids that don't belong to the team are silently ignored.
     *
     * @param int[] $ids
     */
    public function reorder(Team $team, array $ids): void
    {
        $pictures = $this->repository->findBy(['id' => $ids, 'team' => $team]);

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
