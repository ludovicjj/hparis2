<?php

namespace App\Repository;

use App\Entity\Option;
use App\Entity\OptionPicture;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OptionPicture>
 */
class OptionPictureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OptionPicture::class);
    }

    /**
     * @return OptionPicture[]
     */
    public function findByOptionOrdered(Option $option): array
    {
        return $this->createQueryBuilder('op')
            ->where('op.option = :option')
            ->setParameter('option', $option)
            ->orderBy('op.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countByOption(Option $option): int
    {
        return (int) $this->createQueryBuilder('op')
            ->select('COUNT(op.id)')
            ->where('op.option = :option')
            ->setParameter('option', $option)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getNextPositionByOption(Option $option): int
    {
        $lastPosition = $this->createQueryBuilder('op')
            ->select('MAX(op.position)')
            ->where('op.option = :option')
            ->setParameter('option', $option)
            ->getQuery()
            ->getSingleScalarResult();

        return ($lastPosition ?? -1) + 1;
    }

    /**
     * Single query to fetch all pictures for given option ids, grouped by option id.
     * Avoids N+1 when rendering a list of options with their pictures.
     *
     * @param int[] $optionIds
     * @return array<int, OptionPicture[]> keyed by option id
     */
    public function findGroupedByOptionIds(array $optionIds): array
    {
        if (empty($optionIds)) {
            return [];
        }

        $pictures = $this->createQueryBuilder('op')
            ->where('op.option IN (:ids)')
            ->setParameter('ids', $optionIds)
            ->orderBy('op.option', 'ASC')
            ->addOrderBy('op.position', 'ASC')
            ->getQuery()
            ->getResult();

        $grouped = [];
        foreach ($pictures as $picture) {
            $grouped[$picture->getOption()->getId()][] = $picture;
        }

        return $grouped;
    }
}
