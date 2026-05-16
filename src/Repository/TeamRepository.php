<?php

namespace App\Repository;

use App\Entity\Team;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends AbstractEntryRepository<Team>
 */
class TeamRepository extends AbstractEntryRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Team::class);
    }
}
