<?php

namespace App\Controller\front;

use App\Entity\Gallery;
use App\Repository\GalleryRepository;
use App\Repository\PictureRepository;
use App\Service\GalleryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/gallery', name: 'app_front_gallery_')]
class GalleryController extends AbstractController
{
    private const int PICTURES_PER_PAGE = 15;

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(GalleryRepository $galleryRepository): Response
    {
        $galleries = $galleryRepository->findVisibleWithThumbnails();

        return $this->render('front/gallery/index.html.twig', [
            'galleries' => $galleries,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(
        Gallery $gallery,
        Request $request,
        PictureRepository $pictureRepository,
        GalleryService $galleryService,
    ): Response {
        if (!$galleryService->canAccessGallery($gallery, $request->query->get('token'))) {
            return $this->redirectToRoute('app_front_home');
        }

        $pictures = $pictureRepository->findByGalleryPaginated($gallery, 0, self::PICTURES_PER_PAGE);
        $totalPictures = $pictureRepository->countByGallery($gallery);

        return $this->render('front/gallery/show.html.twig', [
            'gallery' => $gallery,
            'pictures' => $pictures,
            'totalPictures' => $totalPictures,
            'hasMore' => $totalPictures > self::PICTURES_PER_PAGE,
            'token' => $request->query->get('token'),
        ]);
    }

    #[Route('/{id}/pictures', name: 'pictures', methods: ['GET'])]
    public function pictures(
        Gallery $gallery,
        Request $request,
        PictureRepository $pictureRepository,
    ): JsonResponse {
        $offset = $request->query->getInt('offset', 0);
        $pictures = $pictureRepository->findByGalleryPaginated($gallery, $offset, self::PICTURES_PER_PAGE);
        $totalPictures = $pictureRepository->countByGallery($gallery);

        $picturesData = array_map(fn($picture) => [
            'id' => $picture->getId(),
            'lightboxPath' => $picture->getLightboxPath(),
            'thumbnailPath' => $picture->getThumbnailPath(),
            'originalName' => $picture->getOriginalName(),
        ], $pictures);

        return $this->json([
            'pictures' => $picturesData,
            'hasMore' => ($offset + self::PICTURES_PER_PAGE) < $totalPictures,
            'nextOffset' => $offset + self::PICTURES_PER_PAGE,
        ]);
    }
}
