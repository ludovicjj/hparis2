<?php

namespace App\Repository;

use App\Entity\Category;
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

    /**
     * @return Gallery[]
     */
    public function findVisibleWithThumbnailsPaginated(?Category $category, int $offset, int $limit): array
    {
        $qb = $this->createQueryBuilder('g')
            ->leftJoin('g.thumbnail', 't')
            ->addSelect('t')
            ->where('g.visibility = true')
            ->orderBy('g.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        if ($category !== null) {
            $qb->innerJoin('g.categories', 'c')
                ->andWhere('c = :category')
                ->setParameter('category', $category);
        }

        return $qb->getQuery()->getResult();
    }

    public function countVisible(?Category $category): int
    {
        $qb = $this->createQueryBuilder('g')
            ->select('COUNT(DISTINCT g.id)')
            ->where('g.visibility = true');

        if ($category !== null) {
            $qb->innerJoin('g.categories', 'c')
                ->andWhere('c = :category')
                ->setParameter('category', $category);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
