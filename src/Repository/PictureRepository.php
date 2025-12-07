<?php

namespace App\Repository;

use App\Entity\Gallery;
use App\Entity\Picture;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Picture>
 */
class PictureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Picture::class);
    }

    public function findOrderedByIds(array $ids)
    {
        $qb = $this->createQueryBuilder('p');
        $order = implode(',', $ids);

        $qb
            ->where('p.id IN (:ids)')
            //->andWhere('p.status = :status')

            //->setParameter('status', Picture::STATUS_PENDING)
            ->setParameter('ids', $ids)

            ->addSelect("FIELD(p.id, $order) AS HIDDEN ord")
            ->orderBy('ord', 'ASC');

        return $qb->getQuery()->getResult();
    }

    public function findByGalleryAndOrderPosition(Gallery $gallery): array
    {
        $qb = $this->createQueryBuilder('p');

        $qb
            ->leftJoin('p.gallery', 'gallery')
            ->andWhere('gallery = :gallery')
            ->orderBy('p.position', 'ASC')
            ->setParameter('gallery', $gallery);

        return $qb->getQuery()->getResult();
    }

    public function findIdsByGallery(Gallery $gallery): array
    {
        return $this->createQueryBuilder('p')
            ->select('p.id')
            ->where('p.gallery = :gallery')
            ->orderBy('p.position', 'ASC')
            ->setParameter('gallery', $gallery)
            ->getQuery()
            ->getSingleColumnResult();
    }

    public function findUnattached(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.gallery IS NULL')
            ->andWhere('p.status = :status')
            ->setParameter('status', Picture::STATUS_PENDING)
            ->getQuery()
            ->getResult();
    }

    public function countByStatus(string $status): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
