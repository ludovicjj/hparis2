<?php

namespace App\Controller\Front;

use App\Entity\Team;
use App\Repository\TeamPictureRepository;
use App\Repository\TeamRepository;
use App\Service\Team\TeamService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/team', name: 'app_front_team_')]
class TeamController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        TeamRepository $teamRepository,
        TeamPictureRepository $teamPictureRepository,
    ): Response {
        $teams = $teamRepository->findPublicActive();
        $ids = array_map(fn (Team $team) => $team->getId(), $teams);
        $picturesByTeamId = $teamPictureRepository->findGroupedByTeamIds($ids);

        return $this->render('front/team/index.html.twig', [
            'teams' => $teams,
            'picturesByTeamId' => $picturesByTeamId,
        ]);
    }

    #[Route('/{id<\d+>}', name: 'show', methods: ['GET'])]
    public function show(
        Team $team,
        Request $request,
        TeamService $teamService,
        TeamPictureRepository $teamPictureRepository,
    ): Response {
        if (!$teamService->canAccessTeam($team, $request->query->get('token'))) {
            return $this->redirectToRoute('app_front_team_index');
        }

        return $this->render('front/team/show.html.twig', [
            'team' => $team,
            'token' => $request->query->get('token'),
            'pictures' => $teamPictureRepository->findByTeamOrdered($team),
        ]);
    }
}
