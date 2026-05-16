<?php

namespace App\Controller\Front;

use App\Repository\VideoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TeamController extends AbstractController
{
    #[Route('/team', name: 'app_front_team_index', methods: ['GET'])]
    public function index(VideoRepository $videoRepository): Response
    {
        return $this->render('front/team/index.html.twig', [
            'videos' => $videoRepository->findPublicActiveByPageSlug('team'),
        ]);
    }
}
