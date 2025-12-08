<?php

namespace App\Controller\admin\Gallery;

use App\Entity\Gallery;
use App\Entity\User;
use App\Form\GalleryType;
use App\Repository\GalleryRepository;
use App\Repository\PictureRepository;
use App\Service\PictureService;
use App\Service\ThumbnailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/admin/gallery', name: 'app_admin_gallery_')]
class GalleryController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        GalleryRepository $galleryRepository,
        PictureService $pictureService,
        #[CurrentUser] User $user
    ): Response {
        $galleries = $galleryRepository->findAllWithThumbnails();
        $galleryCount = $galleryRepository->countAll();

        // Clean Orphan picture created previously
        $pictureService->deleteOrphanPicturesByUser($user);

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
        PictureService $pictureService
    ): Response {
        $gallery = new Gallery();
        $form = $this->createForm(GalleryType::class, $gallery);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle Thumbnail from form data
            $thumbnailService->handle($form);

            // Fetch and bind to Gallery all Pending Pictures into hidden field
            $pictureService->handle($form);

            $entityManager->persist($gallery);
            $entityManager->flush();

            $this->addFlash('success', 'Galerie créée avec succès.');

            return $this->redirectToRoute('app_admin_gallery_index', ['id' => $gallery->getId()]);
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
    ): Response {
        $pictures = $pictureRepository->findByGalleryAndOrderPosition($gallery);
        $pictureIds = $pictureRepository->findIdsByGallery($gallery);

        $form = $this->createForm(GalleryType::class, $gallery);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle Thumbnail from form data
            $thumbnailService->handle($form);

            $pictureService->handle($form);

            $entityManager->flush();
            $this->addFlash('success', 'Galerie modifiée avec succès.');

            return $this->redirectToRoute('app_admin_gallery_index', ['id' => $gallery->getId()]);
        }

        return $this->render('admin/gallery/update.html.twig', [
            'gallery' => $gallery,
            'form' => $form,
            'pictures' => $pictures,
            'pictureIds' => $pictureIds,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Gallery $gallery,
        EntityManagerInterface $entityManager,
        PictureService $pictureService,
        ThumbnailService $thumbnailService
    ): Response
    {
        if ($this->isCsrfTokenValid('delete' . $gallery->getId(), $request->request->get('_token'))) {
            // Remove la thumbnail
            if ($gallery->getThumbnail()) {
                $thumbnailService->deleteFile($gallery->getThumbnail());
            }

            // Remove pictures
            foreach ($gallery->getPictures() as $picture) {
                $pictureService->deleteFile($picture);
            }

            // Will delete Thumbnail + Pictures (cascade)
            $entityManager->remove($gallery);
            $entityManager->flush();

            $this->addFlash('success', 'Galerie supprimée avec succès.');
        }

        return $this->redirectToRoute('app_admin_gallery_index');
    }
}
