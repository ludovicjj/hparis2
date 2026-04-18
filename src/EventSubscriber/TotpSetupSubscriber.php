<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TotpSetupSubscriber implements EventSubscriberInterface
{
    /**
     * Routes that must never trigger the redirect, even when the user is in the "needs setup" state.
     * Prevents infinite redirect loops and keeps logout + setup form + 2FA login reachable.
     */
    private const array WHITELIST_ROUTES = [
        'app_2fa_setup',
        'app_logout',
        '2fa_login',
        '2fa_login_check',
    ];

    public function __construct(
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    public static function getSubscribedEvents(): array
    {
        // Priority 4: runs after Router (32) and FirewallListener (8), so both _route and user are resolved.
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 4],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        // Only fire when 2FA is enabled on the user but no secret yet.
        if (!$user->isTotpEnabled() || $user->getTotpSecret() !== null) {
            return;
        }

        $route = $event->getRequest()->attributes->get('_route');
        if ($route === null) {
            return;
        }

        // Skip Symfony internals: _profiler, _wdt, _preview_error, etc.
        if (str_starts_with($route, '_')) {
            return;
        }

        if (in_array($route, self::WHITELIST_ROUTES, true)) {
            return;
        }

        $event->setResponse(new RedirectResponse(
            $this->urlGenerator->generate('app_2fa_setup')
        ));
    }
}
