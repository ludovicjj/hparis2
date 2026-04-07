<?php

namespace App\Controller\Admin\Gallery;

use App\Entity\Gallery;
use App\Entity\Picture;
use App\Form\GalleryType;
use App\Repository\GalleryRepository;
use App\Repository\PictureRepository;
use App\Service\GalleryService;
use App\Service\PictureService;
use App\Service\ThumbnailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

#[Route('/admin/gallery', name: 'app_admin_gallery_')]
class GalleryController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(GalleryRepository $galleryRepository): Response
    {
        $galleries = $galleryRepository->findAllWithThumbnails();
        $galleryCount = $galleryRepository->countAll();

        return $this->render('admin/gallery/index.html.twig', [
            'galleries' => $galleries,
            'galleryCount' => $galleryCount,
        ]);
    }

    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        ThumbnailService $thumbnailService,
    ): Response {
        $gallery = new Gallery();
        $form = $this->createForm(GalleryType::class, $gallery);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Persist immediately gallery. S3 path required gallery ID
            $entityManager->persist($gallery);
            $entityManager->flush();

            // Handle the cover upload
            $thumbnailService->handle($form);
            $entityManager->flush();

            $this->addFlash('success', 'Votre galerie est prête !');

            return $this->redirectToRoute('app_admin_gallery_update', ['id' => $gallery->getId()]);
        }

        return $this->render('admin/gallery/create.html.twig', [
            'gallery' => $gallery,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/update', name: 'update', methods: ['GET', 'POST'])]
    public function update(
        Request $request,
        Gallery $gallery,
        EntityManagerInterface $entityManager,
        PictureRepository $pictureRepository,
        ThumbnailService $thumbnailService,
        PictureService $pictureService,
        GalleryService $galleryService,
    ): Response {
        $pictures = $pictureRepository->findByGalleryAndOrderPosition($gallery);
        $pictureIds = $pictureRepository->findIdsByGallery($gallery);
        $frontGalleryUrl = $galleryService->generatePublicUrl($gallery);

        $form = $this->createForm(GalleryType::class, $gallery);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle Thumbnail from form data
            $thumbnailService->handle($form);

            // Sort Gallery's pictures
            $pictureService->sortPicture();

            $entityManager->flush();
            $this->addFlash('success', 'Galerie modifiée avec succès.');

            return $this->redirectToRoute('app_admin_gallery_index', ['id' => $gallery->getId()]);
        }

        return $this->render('admin/gallery/update.html.twig', [
            'gallery' => $gallery,
            'form' => $form,
            'pictures' => $pictures,
            'pictureIds' => $pictureIds,
            'front_gallery_url' => $frontGalleryUrl,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Gallery $gallery,
        EntityManagerInterface $entityManager,
        PictureService $pictureService,
        ThumbnailService $thumbnailService
    ): Response {
        if ($this->isCsrfTokenValid('delete' . $gallery->getId(), $request->request->get('_token'))) {
            // Remove la thumbnail S3
            if ($gallery->getThumbnail()) {
                $thumbnailService->deleteFile($gallery->getThumbnail());
            }

            // Remove pictures S3 (TODO)
            foreach ($gallery->getPictures() as $picture) {
                $pictureService->deleteFile($picture);
            }

            // Remove Gallery - cascade delete Thumbnail and Pictures
            $entityManager->remove($gallery);
            $entityManager->flush();

            $this->addFlash('success', 'Galerie supprimée avec succès.');
        }

        return $this->redirectToRoute('app_admin_gallery_index');
    }

    #[Route('/{id}/token', name: 'token', methods: ['POST'])]
    public function resetToken(
        Gallery $gallery,
        EntityManagerInterface $entityManager,
        GalleryService $galleryService
    ): Response
    {
        $gallery->resetToken();
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'url' => $galleryService->generatePublicUrl($gallery),
        ]);
    }

    #[Route('/{id}/pictures/prepare', name: 'prepare_picture', methods: ['POST'])]
    public function preparePicture(
        Request $request,
        Gallery $gallery,
        PictureService $pictureService,
    ): JsonResponse {
        try {
            $payload = $request->toArray();
            $filename = (string) ($payload['filename'] ?? '');
            $contentType = (string) ($payload['contentType'] ?? '');

            $result = $pictureService->prepareUpload($filename, $contentType, $gallery);
        } catch (Throwable $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }

        /** @var Picture $picture */
        $picture = $result['picture'];

        return $this->json([
            'success' => true,
            'pictureId' => $picture->getId(),
            'uploadUrl' => $result['uploadUrl'],
            'originalName' => $picture->getOriginalName(),
            'status' => Picture::STATUS_PROCESSING,
        ]);
    }

}
