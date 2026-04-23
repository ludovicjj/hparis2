<?php

namespace App\Repository;

use App\Entity\Category;
use App\Entity\GalleryCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GalleryCategory>
 */
class GalleryCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GalleryCategory::class);
    }

    /**
     * @param int[] $galleryIds
     * @return GalleryCategory[]
     */
    public function findByCategoryAndGalleryIds(Category $category, array $galleryIds): array
    {
        if (empty($galleryIds)) {
            return [];
        }

        return $this->createQueryBuilder('gc')
            ->andWhere('gc.category = :category')
            ->andWhere('gc.gallery IN (:galleryIds)')
            ->setParameter('category', $category)
            ->setParameter('galleryIds', $galleryIds)
            ->getQuery()
            ->getResult();
    }
}
