<?php

namespace App\Enum;

enum MediaSetting: string
{
    case LOGO = 'logo_path';
    case HERO = 'hero_path';
    case FAVICON = 'favicon_path';

    public function folder(): string
    {
        return match ($this) {
            self::LOGO => 'branding/logo',
            self::HERO => 'branding/hero',
            self::FAVICON => 'branding/favicon',
        };
    }
}
