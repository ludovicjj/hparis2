<?php

namespace App\Repository;

use App\Entity\AbstractEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @template TEntity of AbstractEntry
 * @extends ServiceEntityRepository<TEntity>
 */
abstract class AbstractEntryRepository extends ServiceEntityRepository
{
    /**
     * @return TEntity[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.isDraft = false')
            ->orderBy('e.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return TEntity[]
     */
    public function findPublicActive(): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.active = true')
            ->andWhere('e.visibility = true')
            ->andWhere('e.isDraft = false')
            ->orderBy('e.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getNextPosition(): int
    {
        $lastPosition = $this->createQueryBuilder('e')
            ->select('MAX(e.position)')
            ->getQuery()
            ->getSingleScalarResult();

        return ($lastPosition ?? -1) + 1;
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
