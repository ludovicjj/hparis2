<?php

namespace App\Service;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ImageOptimizerService
{
    public const int BIG_WIDTH = 1200;
    public const int MEDIUM_WITH = 600;
    public const int SMALL_WIDTH = 400;

    private const int QUALITY = 80;

    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    /**
     * Optimise une image : crée version lightbox + thumbnail
     *
     * @param string $sourcePath Original file path (temp file)
     * @param string $destinationDir Dossier de destination
     * @param string $filename Unique filename
     * @return array{lightbox: string, thumbnail: string} Les noms des fichiers créés
     */
    public function optimizePicture(string $sourcePath, string $destinationDir, string $filename): array
    {
        $fileInfo = pathinfo($filename);
        $lightboxFilename = $filename;
        $thumbnailFilename = $fileInfo['filename'] . '-thumb.' . $fileInfo['extension'];

        // 1. Read temp File
        $image = $this->manager->read($sourcePath);

        // 2. Create and upload lightbox version (1200px)
        $image->scaleDown(width: self::BIG_WIDTH);
        $image->toJpeg(quality: self::QUALITY)->save($destinationDir . '/' . $lightboxFilename);

        // 3. Create and upload thumbnail version (400px)
        $image->scaleDown(width: self::SMALL_WIDTH);
        $image->toJpeg(quality: self::QUALITY)->save($destinationDir . '/' . $thumbnailFilename);

        return [
            'lightbox' => $lightboxFilename,
            'thumbnail' => $thumbnailFilename,
        ];
    }

    public function optimizeThumbnail(string $sourcePath, string $destinationDir, string $filename, int $width): void
    {
        $image = $this->manager->read($sourcePath);
        $image->scaleDown(width: $width);
        $image->toJpeg(quality: self::QUALITY)->save($destinationDir . '/' . $filename);
    }
}