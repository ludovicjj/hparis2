<?php

namespace App\Controller\front;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_front_home', methods: ['GET', 'POST'])]
    public function index(): Response
    {
        return $this->render('front/home/index.html.twig');
    }
}