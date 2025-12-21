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

    public function countByStatus(string $status): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return Picture[]
     */
    public function findByGalleryPaginated(Gallery $gallery, int $offset = 0, int $limit = 15): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.gallery = :gallery')
            ->andWhere('p.status = :status')
            ->setParameter('gallery', $gallery)
            ->setParameter('status', Picture::STATUS_READY)
            ->orderBy('p.position', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByGallery(Gallery $gallery): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.gallery = :gallery')
            ->andWhere('p.status = :status')
            ->setParameter('gallery', $gallery)
            ->setParameter('status', Picture::STATUS_READY)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
