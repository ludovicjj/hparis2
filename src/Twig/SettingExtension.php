<?php

namespace App\Twig;

use App\Enum\MediaSetting;
use App\Service\MediaSettingService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class SettingExtension extends AbstractExtension
{
    public function __construct(
        private MediaSettingService $mediaSettingService,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('logo_url', fn (): ?string => $this->mediaSettingService->getPublicUrl(MediaSetting::LOGO)),
            new TwigFunction('hero_url', fn (): ?string => $this->mediaSettingService->getPublicUrl(MediaSetting::HERO)),
        ];
    }
}
