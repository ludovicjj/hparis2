<?php

namespace App\Service\Team;

use App\Entity\Team;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

readonly class TeamService
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Generate the public URL for a team.
     * If the team is private (visibility = false), the token is included as a query parameter.
     */
    public function generatePublicUrl(Team $team): string
    {
        $params = ['id' => $team->getId()];

        if (!$team->isVisibility()) {
            $params['token'] = $team->getToken();
        }

        return $this->urlGenerator->generate(
            'app_front_team_show',
            $params,
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    public function canAccessTeam(Team $team, ?string $token): bool
    {
        if ($team->isVisibility()) {
            return true;
        }

        $teamToken = $team->getToken();
        if ($teamToken === null || $token === null) {
            return false;
        }

        return hash_equals($teamToken, $token);
    }
}
