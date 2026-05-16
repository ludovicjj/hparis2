<?php

namespace App\Repository;

use App\Entity\Video;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Video>
 */
class VideoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Video::class);
    }

    /**
     * @return Video[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('v')
            ->orderBy('v.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Video[]
     */
    public function findPublicActive(): array
    {
        return $this->createQueryBuilder('v')
            ->where('v.active = true')
            ->andWhere('v.visibility = true')
            ->orderBy('v.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getNextPosition(): int
    {
        $lastPosition = $this->createQueryBuilder('v')
            ->select('MAX(v.position)')
            ->getQuery()
            ->getSingleScalarResult();

        return ($lastPosition ?? -1) + 1;
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
