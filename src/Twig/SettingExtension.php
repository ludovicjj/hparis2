<?php

namespace App\Twig;

use App\Enum\BrandSetting;
use App\Enum\MediaSetting;
use App\Service\Setting\BrandSettingService;
use App\Service\Setting\MediaSettingService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class SettingExtension extends AbstractExtension
{
    /** @var array<string, ?string> */
    private array $cache = [];

    public function __construct(
        private readonly MediaSettingService $mediaSettingService,
        private readonly BrandSettingService $brandSettingService,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('logo_url', fn (): ?string => $this->resolveMedia(MediaSetting::LOGO)),
            new TwigFunction('hero_url', fn (): ?string => $this->resolveMedia(MediaSetting::HERO)),
            new TwigFunction('favicon_url', fn (): ?string => $this->resolveMedia(MediaSetting::FAVICON)),
            new TwigFunction('site_name', fn (): ?string => $this->resolveBrand(BrandSetting::SITE_NAME)),
            new TwigFunction('gsc_token', fn (): ?string => $this->resolveBrand(BrandSetting::GSC_TOKEN)),
        ];
    }

    private function resolveMedia(MediaSetting $type): ?string
    {
        $key = $type->value;

        if (!array_key_exists($key, $this->cache)) {
            $this->cache[$key] = $this->mediaSettingService->getPublicUrl($type);
        }

        return $this->cache[$key];
    }

    private function resolveBrand(BrandSetting $type): ?string
    {
        $key = $type->value;

        if (!array_key_exists($key, $this->cache)) {
            $this->cache[$key] = $this->brandSettingService->get($type);
        }

        return $this->cache[$key];
    }
}
