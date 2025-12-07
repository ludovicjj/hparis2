<?php

namespace App\Repository;

use App\Entity\Gallery;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Gallery>
 */
class GalleryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Gallery::class);
    }

    /**
     * @return array<array{0: Gallery, picturesCount: int}>
     */
    public function findAllWithThumbnails(): array
    {
        return $this->createQueryBuilder('g')
            ->leftJoin('g.thumbnail', 't')
            ->leftJoin('g.pictures', 'p')

            ->addSelect('t')
            ->addSelect('COUNT(p.id) AS picturesCount')

            ->groupBy('g.id')
            ->orderBy('g.createdAt', 'DESC')

            ->getQuery()
            ->getResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
