<?php

namespace App\Controller\Front;

use App\Enum\MediaSetting;
use App\Service\MediaSettingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FaviconController extends AbstractController
{
    #[Route('/favicon.ico', name: 'app_front_favicon', methods: ['GET'])]
    public function favicon(MediaSettingService $mediaSettingService): Response
    {
        $url = $mediaSettingService->getPublicUrl(MediaSetting::FAVICON);

        if (!$url) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $response = new RedirectResponse($url, Response::HTTP_FOUND);
        $response->setPublic();
        $response->setMaxAge(86400);

        return $response;
    }
}
