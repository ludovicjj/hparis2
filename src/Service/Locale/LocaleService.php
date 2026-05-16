<?php

namespace App\Service\Locale;

use Symfony\Component\HttpFoundation\Request;

class LocaleService
{
    public const array SUPPORTED_LOCALES = ['fr', 'en'];

    public const string COOKIE_NAME = 'locale';

    /**
     * Returns the Referer when its host matches the current request host, otherwise
     * the fallback URL. Referer is client-controlled — trusting it blindly opens up
     * an open-redirect vector (attacker hosts a form on evil.com pointing at our
     * endpoint, browser sets Referer to evil.com, we'd redirect the user there).
     */
    public function resolve(Request $request, string $fallback): string
    {
        $referer = $request->headers->get('referer');
        if ($referer === null) {
            return $fallback;
        }

        $refererHost = parse_url($referer, PHP_URL_HOST);
        if ($refererHost === null || $refererHost !== $request->getHost()) {
            return $fallback;
        }

        return $referer;
    }
}
