<?php

namespace App\Controller\Front;

use App\Repository\CategoryRepository;
use App\Repository\GalleryRepository;
use App\Service\GalleryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SitemapController extends AbstractController
{
    #[Route('/sitemap.xml', name: 'app_front_sitemap', methods: ['GET'])]
    public function index(
        GalleryRepository $galleryRepository,
        CategoryRepository $categoryRepository,
        GalleryService $galleryService,
    ): Response {
        $urls = [];

        $urls[] = [
            'loc' => $this->generateUrl('app_front_home', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'changefreq' => 'weekly',
            'priority' => '1.0',
        ];

        $urls[] = [
            'loc' => $this->generateUrl('app_front_gallery_index', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'changefreq' => 'weekly',
            'priority' => '0.9',
        ];

        $urls[] = [
            'loc' => $this->generateUrl('app_front_contact', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'changefreq' => 'monthly',
            'priority' => '0.6',
        ];

        foreach ($categoryRepository->findVisibleOrdered() as $category) {
            $urls[] = [
                'loc' => $this->generateUrl(
                    'app_front_gallery_index',
                    ['category' => $category->getSlug()],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ),
                'changefreq' => 'weekly',
                'priority' => '0.7',
            ];
        }

        $galleries = $galleryRepository->findBy(['visibility' => true]);
        foreach ($galleries as $gallery) {
            $urls[] = [
                'loc' => $galleryService->generatePublicUrl($gallery),
                'lastmod' => $gallery->getUpdatedAt()?->format('Y-m-d'),
                'changefreq' => 'monthly',
                'priority' => '0.8',
            ];
        }

        $response = $this->render('front/sitemap.xml.twig', ['urls' => $urls]);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');

        return $response;
    }
}
