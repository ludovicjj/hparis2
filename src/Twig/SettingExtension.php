<?php

namespace App\Twig;

use App\Enum\MediaSetting;
use App\Service\MediaSettingService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class SettingExtension extends AbstractExtension
{
    /** @var array<string, ?string> */
    private array $cache = [];

    public function __construct(
        private readonly MediaSettingService $mediaSettingService,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('logo_url', fn (): ?string => $this->resolve(MediaSetting::LOGO)),
            new TwigFunction('hero_url', fn (): ?string => $this->resolve(MediaSetting::HERO)),
            new TwigFunction('favicon_url', fn (): ?string => $this->resolve(MediaSetting::FAVICON)),
        ];
    }

    private function resolve(MediaSetting $type): ?string
    {
        $key = $type->value;

        if (!array_key_exists($key, $this->cache)) {
            $this->cache[$key] = $this->mediaSettingService->getPublicUrl($type);
        }

        return $this->cache[$key];
    }
}
