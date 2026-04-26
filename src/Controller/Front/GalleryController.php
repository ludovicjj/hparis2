<?php

namespace App\Controller\Front;

use App\Entity\Gallery;
use App\Repository\CategoryRepository;
use App\Repository\GalleryRepository;
use App\Repository\PictureRepository;
use App\Service\GalleryService;
use App\Service\S3Service;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/gallery', name: 'app_front_gallery_')]
class GalleryController extends AbstractController
{
    private const int PICTURES_PER_PAGE = 15;
    private const int GALLERIES_PER_PAGE = 6;

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        GalleryRepository $galleryRepository,
        CategoryRepository $categoryRepository,
    ): Response {
        $categories = $categoryRepository->findVisibleOrdered();
        $slug = $request->query->get('category');
        $activeCategory = $slug ? $categoryRepository->findOneBy(['slug' => $slug, 'visibility' => true]) : null;

        if ($activeCategory === null && !empty($categories)) {
            return $this->redirectToRoute('app_front_gallery_index', ['category' => $categories[0]->getSlug()]);
        }

        $galleries = $galleryRepository->findVisibleWithThumbnailsPaginated($activeCategory, 0, self::GALLERIES_PER_PAGE);
        $total = $galleryRepository->countVisible($activeCategory);

        return $this->render('front/gallery/index.html.twig', [
            'galleries' => $galleries,
            'categories' => $categories,
            'activeCategory' => $activeCategory,
            'hasMore' => count($galleries) < $total,
            'nextOffset' => count($galleries),
        ]);
    }

    #[Route('/api/list', name: 'list', methods: ['GET'])]
    public function list(
        Request $request,
        GalleryRepository $galleryRepository,
        CategoryRepository $categoryRepository,
        S3Service $s3Service,
        GalleryService $galleryService,
    ): JsonResponse {
        $offset = max(0, $request->query->getInt('offset'));
        $slug = $request->query->get('category');

        $activeCategory = $slug ? $categoryRepository->findOneBy(['slug' => $slug, 'visibility' => true]) : null;

        $galleries = $galleryRepository->findVisibleWithThumbnailsPaginated(
            category: $activeCategory,
            offset: $offset,
            limit: self::GALLERIES_PER_PAGE
        );

        $total = $galleryRepository->countVisible($activeCategory);
        $showParams = $activeCategory ? ['category' => $activeCategory->getSlug()] : [];

        $payload = array_map(fn(Gallery $gallery) => [
            'id' => $gallery->getId(),
            'title' => $gallery->getTitle(),
            'url' => $this->generateUrl('app_front_gallery_show', ['id' => $gallery->getId(), 'slug' => $galleryService->resolveSlug($gallery)] + $showParams),
            'thumbnailUrl' => $gallery->getThumbnail() ? $s3Service->getPublicUrl($gallery->getThumbnail()->getFilename()) : null,
        ], $galleries);

        return $this->json([
            'galleries' => $payload,
            'hasMore' => ($offset + self::GALLERIES_PER_PAGE) < $total,
            'nextOffset' => $offset + self::GALLERIES_PER_PAGE,
        ]);
    }

    #[Route('/{id<\d+>}/{slug}', name: 'show', requirements: ['slug' => '[a-z0-9-]+'], methods: ['GET'])]
    public function show(
        Gallery $gallery,
        string $slug,
        Request $request,
        PictureRepository $pictureRepository,
        GalleryService $galleryService,
    ): Response {
        if (!$galleryService->canAccessGallery($gallery, $request->query->get('token'))) {
            return $this->redirectToRoute('app_front_home');
        }

        $expectedSlug = $galleryService->resolveSlug($gallery);

        if ($slug !== $expectedSlug) {
            return $this->redirectToRoute(
                'app_front_gallery_show',
                ['id' => $gallery->getId(), 'slug' => $expectedSlug] + $request->query->all(),
                Response::HTTP_MOVED_PERMANENTLY,
            );
        }

        $pictures = $pictureRepository->findByGalleryPaginated($gallery, 0, self::PICTURES_PER_PAGE);
        $totalPictures = $pictureRepository->countByGallery($gallery);
        $backParams = $request->query->get('category') ? ['category' => $request->query->get('category')] : [];

        $pictureLightboxPaths = $gallery->isVisibility()
            ? $pictureRepository->findReadyLightboxPathsByGallery($gallery)
            : [];

        return $this->render('front/gallery/show.html.twig', [
            'gallery' => $gallery,
            'pictures' => $pictures,
            'hasMore' => $totalPictures > self::PICTURES_PER_PAGE,
            'token' => $request->query->get('token'),
            'backParams' => $backParams,
            'pictureLightboxPaths' => $pictureLightboxPaths,
        ]);
    }

    #[Route('/{id<\d+>}', name: 'show_legacy', methods: ['GET'])]
    public function showLegacy(
        Gallery $gallery,
        Request $request,
        GalleryService $galleryService,
    ): Response {
        if (!$galleryService->canAccessGallery($gallery, $request->query->get('token'))) {
            return $this->redirectToRoute('app_front_home');
        }

        return $this->redirectToRoute(
            'app_front_gallery_show',
            ['id' => $gallery->getId(), 'slug' => $galleryService->resolveSlug($gallery)] + $request->query->all(),
            Response::HTTP_MOVED_PERMANENTLY,
        );
    }

    #[Route('/api/{id<\d+>}/pictures', name: 'pictures', methods: ['GET'])]
    public function pictures(
        Gallery $gallery,
        Request $request,
        PictureRepository $pictureRepository,
        S3Service $s3Service,
    ): JsonResponse {
        $offset = $request->query->getInt('offset', 0);
        $pictures = $pictureRepository->findByGalleryPaginated($gallery, $offset, self::PICTURES_PER_PAGE);
        $totalPictures = $pictureRepository->countByGallery($gallery);

        // Build absolute S3 URLs server-side so the JS can use them as-is in <img src> / <a href>.
        $picturesData = array_map(fn($picture) => [
            'id' => $picture->getId(),
            'lightboxPath' => $s3Service->getPublicUrl($picture->getLightboxPath()),
            'thumbnailPath' => $s3Service->getPublicUrl($picture->getThumbnailPath()),
            'originalName' => $picture->getOriginalName(),
        ], $pictures);

        return $this->json([
            'pictures' => $picturesData,
            'hasMore' => ($offset + self::PICTURES_PER_PAGE) < $totalPictures,
            'nextOffset' => $offset + self::PICTURES_PER_PAGE,
        ]);
    }
}
