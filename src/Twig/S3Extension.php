<?php

namespace App\Twig;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class S3Extension extends AbstractExtension
{
    public function __construct(
        #[Autowire('%env(AWS_S3_PUBLIC_URL)%')]
        private readonly string $publicUrl,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('s3_url', $this->s3Url(...)),
        ];
    }

    /**
     * Build the public URL of an S3 object from its key.
     * Returns an empty string if the key is null or empty (lets templates fallback gracefully).
     */
    public function s3Url(?string $key): string
    {
        if ($key === null || $key === '') {
            return '';
        }

        return rtrim($this->publicUrl, '/') . '/' . ltrim($key, '/');
    }
}
