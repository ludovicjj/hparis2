<?php

namespace App\Repository;

use App\Entity\Setting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Setting>
 */
class SettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Setting::class);
    }

    public function findOneByType(string $type): ?Setting
    {
        try {
            return $this->createQueryBuilder('s')
                ->andWhere('s.type = :type')
                ->setParameter('type', $type)
                ->getQuery()
                ->getOneOrNullResult();
        } catch (NoResultException) {
            return null;
        }
    }
}
