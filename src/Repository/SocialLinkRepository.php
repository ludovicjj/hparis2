<?php

namespace App\Repository;

use App\Entity\SocialLink;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SocialLink>
 */
class SocialLinkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SocialLink::class);
    }

    /**
     * @return SocialLink[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return SocialLink[]
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.active = true')
            ->orderBy('s.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getNextPosition(): int
    {
        $lastPosition = $this->createQueryBuilder('s')
            ->select('MAX(s.position)')
            ->getQuery()
            ->getSingleScalarResult();

        return ($lastPosition ?? -1) + 1;
    }
}
