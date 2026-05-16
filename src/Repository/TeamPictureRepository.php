<?php

namespace App\Repository;

use App\Entity\Team;
use App\Entity\TeamPicture;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TeamPicture>
 */
class TeamPictureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TeamPicture::class);
    }

    /**
     * @return TeamPicture[]
     */
    public function findByTeamOrdered(Team $team): array
    {
        return $this->createQueryBuilder('tp')
            ->where('tp.team = :team')
            ->setParameter('team', $team)
            ->orderBy('tp.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countByTeam(Team $team): int
    {
        return (int) $this->createQueryBuilder('tp')
            ->select('COUNT(tp.id)')
            ->where('tp.team = :team')
            ->setParameter('team', $team)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getNextPositionByTeam(Team $team): int
    {
        $lastPosition = $this->createQueryBuilder('tp')
            ->select('MAX(tp.position)')
            ->where('tp.team = :team')
            ->setParameter('team', $team)
            ->getQuery()
            ->getSingleScalarResult();

        return ($lastPosition ?? -1) + 1;
    }

    /**
     * Single query to fetch all pictures for given team ids, grouped by team id.
     * Avoids N+1 when rendering a list of teams with their pictures.
     *
     * @param int[] $teamIds
     * @return array<int, TeamPicture[]> keyed by team id
     */
    public function findGroupedByTeamIds(array $teamIds): array
    {
        if (empty($teamIds)) {
            return [];
        }

        $pictures = $this->createQueryBuilder('tp')
            ->where('tp.team IN (:ids)')
            ->setParameter('ids', $teamIds)
            ->orderBy('tp.team', 'ASC')
            ->addOrderBy('tp.position', 'ASC')
            ->getQuery()
            ->getResult();

        $grouped = [];
        foreach ($pictures as $picture) {
            $grouped[$picture->getTeam()->getId()][] = $picture;
        }

        return $grouped;
    }
}
