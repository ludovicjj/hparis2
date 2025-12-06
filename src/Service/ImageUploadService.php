<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class ImageUploadService
{
    private const ALLOWED_TYPES = ['jpg', 'jpeg', 'png'];
    private const THUMBNAIL_MAX_WIDTH = 400;
    private const THUMBNAIL_MAX_HEIGHT = 400;

    public function __construct(
        private readonly SluggerInterface $slugger,
        private readonly string $uploadDirectory,
    ) {
    }

    public function uploadPicture(UploadedFile $file): array
    {
        $this->validateFile($file);

        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = strtolower($file->guessExtension() ?? $file->getClientOriginalExtension());
        $safeFilename = $this->slugger->slug($originalName);
        $filename = $safeFilename . '-' . uniqid() . '.' . $extension;

        $picturesDir = $this->uploadDirectory . '/galleries';
        $this->ensureDirectoryExists($picturesDir);

        $file->move($picturesDir, $filename);

        return [
            'filename' => $filename,
            'originalName' => $file->getClientOriginalName(),
            'type' => $extension,
        ];
    }

    public function uploadThumbnail(UploadedFile $file): array
    {
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = strtolower($file->guessExtension() ?? $file->getClientOriginalExtension());
        $safeFilename = $this->slugger->slug($originalName);
        $filename = $safeFilename . '-' . uniqid() . '.' . $extension;

        $thumbnailsDir = $this->uploadDirectory . '/galleries/thumbnails';
        $this->ensureDirectoryExists($thumbnailsDir);

        // Déplacer le fichier temporairement
        $tempPath = $file->getPathname();
        $targetPath = $thumbnailsDir . '/' . $filename;

        // Créer une miniature redimensionnée
        $this->createResizedImage($tempPath, $targetPath, $extension);

        return [
            'filename' => $filename,
            'originalName' => $file->getClientOriginalName(),
            'type' => $extension,
        ];
    }

    public function deletePicture(string $filename): void
    {
        $path = $this->uploadDirectory . '/galleries/' . $filename;
        if (file_exists($path)) {
            unlink($path);
        }
    }

    public function deleteThumbnail(string $filename): void
    {
        $path = $this->uploadDirectory . '/galleries/thumbnails/' . $filename;
        if (file_exists($path)) {
            unlink($path);
        }
    }

    private function validateFile(UploadedFile $file): void
    {
        $extension = strtolower($file->guessExtension() ?? $file->getClientOriginalExtension());

        if (!in_array($extension, self::ALLOWED_TYPES, true)) {
            throw new \InvalidArgumentException(
                sprintf('Type de fichier non autorisé. Types acceptés : %s', implode(', ', self::ALLOWED_TYPES))
            );
        }
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    private function createResizedImage(string $sourcePath, string $targetPath, string $extension): void
    {
        $sourceImage = match ($extension) {
            'jpg', 'jpeg' => imagecreatefromjpeg($sourcePath),
            'png' => imagecreatefrompng($sourcePath),
            default => throw new \InvalidArgumentException('Type non supporté'),
        };

        if ($sourceImage === false) {
            throw new \RuntimeException('Impossible de lire l\'image source');
        }

        $originalWidth = imagesx($sourceImage);
        $originalHeight = imagesy($sourceImage);

        // Calculer les nouvelles dimensions en conservant le ratio
        $ratio = min(
            self::THUMBNAIL_MAX_WIDTH / $originalWidth,
            self::THUMBNAIL_MAX_HEIGHT / $originalHeight
        );

        // Ne pas agrandir si l'image est plus petite
        if ($ratio >= 1) {
            $newWidth = $originalWidth;
            $newHeight = $originalHeight;
        } else {
            $newWidth = (int) ($originalWidth * $ratio);
            $newHeight = (int) ($originalHeight * $ratio);
        }

        $resizedImage = imagecreatetruecolor($newWidth, $newHeight);

        // Conserver la transparence pour les PNG
        if ($extension === 'png') {
            imagealphablending($resizedImage, false);
            imagesavealpha($resizedImage, true);
        }

        imagecopyresampled(
            $resizedImage,
            $sourceImage,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $originalWidth, $originalHeight
        );

        match ($extension) {
            'jpg', 'jpeg' => imagejpeg($resizedImage, $targetPath, 85),
            'png' => imagepng($resizedImage, $targetPath, 8),
        };

        imagedestroy($sourceImage);
        imagedestroy($resizedImage);
    }
}
