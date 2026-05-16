<?php

namespace App\Controller\Front;

use App\Repository\VideoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class OptionsController extends AbstractController
{
    #[Route('/options', name: 'app_front_options_index', methods: ['GET'])]
    public function index(VideoRepository $videoRepository): Response
    {
        return $this->render('front/options/index.html.twig', [
            'videos' => $videoRepository->findPublicActiveByPageSlug('options'),
        ]);
    }
}
