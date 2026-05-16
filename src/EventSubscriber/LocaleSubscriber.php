<?php

namespace App\EventSubscriber;

use App\Controller\Front\LocaleController;
use App\Service\Locale\LocaleService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class LocaleSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        // Priority 15: runs just after Symfony's built-in LocaleListener (priority 16),
        // so the cookie value overrides the default locale set by the framework.
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 15]],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $locale = $request->cookies->get(LocaleService::COOKIE_NAME);

        if (in_array($locale, LocaleService::SUPPORTED_LOCALES, true)) {
            $request->setLocale($locale);
        }
    }
}
