<?php

namespace App\Controller\Front;

use App\Service\Locale\LocaleService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LocaleController extends AbstractController
{
    #[Route('/locale/{locale}', name: 'app_locale_switch', requirements: ['locale' => 'fr|en'], methods: ['POST'])]
    public function switch(string $locale, Request $request, LocaleService $redirectResolver): Response
    {
        // Security
        if (!$this->isCsrfTokenValid('locale_switch', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $response = new RedirectResponse(
            $redirectResolver->resolve($request, $this->generateUrl('app_front_home'))
        );

        $response->headers->setCookie(
            Cookie::create(LocaleService::COOKIE_NAME)
                ->withValue($locale)
                ->withExpires(strtotime('+1 year'))
                ->withPath('/')
                ->withSameSite(Cookie::SAMESITE_LAX)
        );

        return $response;
    }
}
