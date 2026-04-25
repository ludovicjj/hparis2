<?php

namespace App\Service;

use App\Entity\Gallery;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

readonly class GalleryService
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Generate the public URL for a gallery.
     * If the gallery is private (visibility = false), the token is included as a query parameter.
     */
    public function generatePublicUrl(Gallery $gallery): string
    {
        $params = ['id' => $gallery->getId()];

        if (!$gallery->isVisibility()) {
            $params['token'] = $gallery->getToken();
        }

        return $this->urlGenerator->generate(
            'app_front_gallery_show',
            $params,
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    public function canAccessGallery(Gallery $gallery, ?string $token): bool
    {
        if ($gallery->isVisibility()) {
            return true;
        }

        return $token !== null && hash_equals($gallery->getToken(), $token);
    }

    /**
     * Extract the admin gallery list filter (category slug / uncategorized flag)
     * from the current request, to be forwarded across navigation and redirects.
     *
     * @return array{category?: string, uncategorized?: int}
     */
    public function extractAdminFilterParams(Request $request): array
    {
        $params = [];
        if ($category = $request->query->get('category')) {
            $params['category'] = $category;
        }
        if ($request->query->getBoolean('uncategorized')) {
            $params['uncategorized'] = 1;
        }

        return $params;
    }
}
