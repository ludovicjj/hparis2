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
     * Resize a raw binary image into two JPEG variants entirely in memory:
     *  - lightbox: scaled down to BIG_WIDTH (1200px)
     *  - thumbnail: scaled down further to SMALL_WIDTH (400px)
     *
     * Both variants are returned as raw JPEG binary strings, ready to be uploaded
     * to object storage. No temporary files are written to disk.
     *
     * @return array{lightbox: string, thumbnail: string}
     */
    public function optimizePicture(string $sourceContent): array
    {
        $image = $this->manager->read($sourceContent);

        // Lightbox variant (1200px max)
        $image->scaleDown(width: self::BIG_WIDTH);
        $lightboxBinary = (string) $image->toJpeg(quality: self::QUALITY);

        // Thumbnail variant (400px max). The same image instance is reused: scaling
        // a 1200px image down to 400px keeps quality and is ~3x faster than
        // re-decoding the original.
        $image->scaleDown(width: self::SMALL_WIDTH);
        $thumbnailBinary = (string) $image->toJpeg(quality: self::QUALITY);

        return [
            'lightbox' => $lightboxBinary,
            'thumbnail' => $thumbnailBinary,
        ];
    }

    public function optimizeThumbnail(string $sourceContent, int $maxWidth): string
    {
        $image = $this->manager->read($sourceContent);
        $image->scaleDown(width: $maxWidth);

        return (string) $image->toJpeg(quality: self::QUALITY);
    }
}