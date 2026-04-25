<?php

namespace App\Twig;

use App\Entity\SocialLink;
use App\Repository\SocialLinkRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class SocialLinksExtension extends AbstractExtension
{
    /** @var SocialLink[]|null */
    private ?array $cache = null;

    public function __construct(
        private readonly SocialLinkRepository $socialLinkRepository,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('social_links', [$this, 'getActiveSocialLinks']),
        ];
    }

    /**
     * @return SocialLink[]
     */
    public function getActiveSocialLinks(): array
    {
        return $this->cache ??= $this->socialLinkRepository->findActive();
    }
}
