<?php

namespace App\Service;

use Endroid\QrCode\Builder\Builder;

readonly class QrCodeService
{
    public function generateDataUri(string $data, int $size = 200): string
    {
        return new Builder()
            ->build(data: $data, size: $size, margin: 10)
            ->getDataUri();
    }
}
