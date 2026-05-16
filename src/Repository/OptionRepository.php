<?php

namespace App\Repository;

use App\Entity\Option;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends AbstractEntryRepository<Option>
 */
class OptionRepository extends AbstractEntryRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Option::class);
    }
}
