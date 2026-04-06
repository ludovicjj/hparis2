<?php

namespace App\Twig;

use App\Repository\SocialLinkRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class SocialLinksExtension extends AbstractExtension
{
    public function __construct(
        private SocialLinkRepository $socialLinkRepository,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('social_links', $this->getActiveSocialLinks(...)),
        ];
    }

    /**
     * @return \App\Entity\SocialLink[]
     */
    public function getActiveSocialLinks(): array
    {
        return $this->socialLinkRepository->findActive();
    }
}
