<?php

namespace App\Controller\admin\Gallery;

use App\Entity\Gallery;
use App\Form\GalleryType;
use App\Repository\GalleryRepository;
use App\Repository\PictureRepository;
use App\Service\ImageUploadService;
use App\Service\PictureService;
use App\Service\ThumbnailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/gallery', name: 'app_admin_gallery_')]
class GalleryController extends AbstractController
{
    public function __construct(
        private readonly ImageUploadService $uploadService,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(GalleryRepository $galleryRepository): Response
    {
        $galleries = $galleryRepository->findAllWithThumbnails();

        return $this->render('admin/gallery/index.html.twig', [
            'galleries' => $galleries,
        ]);
    }

    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        ThumbnailService $thumbnailService,
        PictureService $pictureService
    ): Response {
        $gallery = new Gallery();
        $form = $this->createForm(GalleryType::class, $gallery);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $thumbnailService->handle($form);
            $pictureService->handle($form);

            $entityManager->persist($gallery);
            $entityManager->flush();

            $this->addFlash('success', 'Galerie créée avec succès.');

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
        ThumbnailService $thumbnailService,
        PictureRepository $pictureRepository,
    ): Response {
        $pictures = $pictureRepository->findByGalleryAndOrderPosition($gallery);
        $form = $this->createForm(GalleryType::class, $gallery);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $thumbnailService->handle($form);
            $entityManager->flush();
            $this->addFlash('success', 'Galerie modifiée avec succès.');

            return $this->redirectToRoute('app_admin_gallery_update', ['id' => $gallery->getId()]);
        }

        return $this->render('admin/gallery/update.html.twig', [
            'gallery' => $gallery,
            'form' => $form,
            'pictures' => $pictures
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Gallery $gallery, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $gallery->getId(), $request->request->get('_token'))) {
            // Supprimer la thumbnail
            if ($gallery->getThumbnail()) {
                $this->uploadService->deleteThumbnail($gallery->getThumbnail()->getFilename());
            }

            // Supprimer les pictures
            foreach ($gallery->getPictures() as $picture) {
                $this->uploadService->deletePicture($picture->getFilename());
            }

            $entityManager->remove($gallery);
            $entityManager->flush();

            $this->addFlash('success', 'Galerie supprimée avec succès.');
        }

        return $this->redirectToRoute('app_admin_gallery_index');
    }
}
