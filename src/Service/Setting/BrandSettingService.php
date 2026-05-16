<?php

namespace App\Service\Setting;

use App\Entity\Setting;
use App\Enum\BrandSetting;
use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;

readonly class BrandSettingService
{
    public function __construct(
        private SettingRepository $settingRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function get(BrandSetting $type): ?string
    {
        return $this->settingRepository->findOneByType($type->value)?->getValue();
    }

    public function save(BrandSetting $type, ?string $value): void
    {
        $value = $value !== null ? trim($value) : null;
        $value = $value === '' ? null : $value;

        $setting = $this->settingRepository->findOneByType($type->value);

        if ($setting === null) {
            $setting = new Setting();
            $setting->setType($type->value);
            $this->entityManager->persist($setting);
        }

        $setting->setValue($value);

        $this->entityManager->flush();
    }
}
