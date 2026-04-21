<?php

namespace App\Service;

use App\Entity\Setting;
use App\Enum\MediaSetting;
use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

readonly class MediaSettingService
{
    public function __construct(
        private SluggerInterface $slugger,
        private S3Service $s3Service,
        private SettingRepository $settingRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function handle(FormInterface $form, MediaSetting $type): void
    {
        /** @var ?UploadedFile $file */
        $file = $form->get('file')->getData();

        if (!$file) {
            return;
        }

        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalName);
        $extension = $file->guessExtension();

        $key = sprintf('%s/%s-%s.%s', $type->folder(), $safeFilename, uniqid(), $extension);

        if (!$this->s3Service->uploadPublicFileFromPath($key, $file->getPathname(), $file->getMimeType())) {
            throw new RuntimeException('Failed to upload media to object storage.');
        }

        $setting = $this->settingRepository->findOneByType($type->value);

        if ($setting) {
            $this->s3Service->deleteFile($setting->getValue());
            $setting->setValue($key);
        } else {
            $setting = new Setting();
            $setting->setType($type->value);
            $setting->setValue($key);
            $this->entityManager->persist($setting);
        }

        $this->entityManager->flush();
    }

    public function getPublicUrl(MediaSetting $type): ?string
    {
        $setting = $this->settingRepository->findOneByType($type->value);
        $key = $setting?->getValue();

        if (!$key) {
            return null;
        }

        return $this->s3Service->getPublicUrl($key);
    }

    public function delete(MediaSetting $type): void
    {
        $setting = $this->settingRepository->findOneByType($type->value);

        if (!$setting) {
            return;
        }

        $this->s3Service->deleteFile($setting->getValue());
        $this->entityManager->remove($setting);
        $this->entityManager->flush();
    }
}
