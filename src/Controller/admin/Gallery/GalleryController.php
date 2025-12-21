<?php

namespace App\Controller\admin\Gallery;

use App\Entity\Gallery;
use App\Entity\Picture;
use App\Entity\User;
use App\Form\GalleryType;
use App\Message\ProcessPictureMessage;
use App\Repository\GalleryRepository;
use App\Repository\PictureRepository;
use App\Service\GalleryService;
use App\Service\PictureService;
use App\Service\ThumbnailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
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

            $entityManager->persist($gallery);
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

    #[Route('/{id}/token', name: 'token', methods: ['POST'])]
    public function resetToken(Gallery $gallery, EntityManagerInterface $entityManager, GalleryService $galleryService): Response
    {
        $gallery->resetToken();
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'url' => $galleryService->generatePublicUrl($gallery),
        ]);
    }

    #[Route('/{id}/pictures', name: 'add_picture', methods: ['POST'])]
    public function addPicture(
        Request $request,
        Gallery $gallery,
        EntityManagerInterface $entityManager,
        MessageBusInterface $messageBus,
        SluggerInterface $slugger,
        Filesystem $filesystem,
        Security $security,
        #[Autowire('%upload_directory%')] string $uploadDirectory,
    ): JsonResponse {
        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');

        // 1. Validation
        if (!$file || !$file->isValid()) {
            return $this->json(['success' => false, 'error' => 'Fichier invalide'], 400);
        }

        $allowedMimeTypes = ['image/jpeg', 'image/png'];
        if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
            return $this->json(['success' => false, 'error' => 'Type de fichier non autorisé'], 400);
        }

        // 2. Générer filename unique
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = strtolower($file->guessExtension() ?? $file->getClientOriginalExtension());
        $safeFilename = $slugger->slug($originalName)->slice(0, 100);
        $filename = $safeFilename . '-' . uniqid() . '.' . $extension;

        // 3. Sauver dans /temp/
        $tempDir = $uploadDirectory . '/temp';
        $filesystem->mkdir($tempDir);
        $file->move($tempDir, $filename);

        // 4. Créer Picture liée à la Gallery
        $picture = new Picture();
        $picture->setFilename($filename);
        $picture->setOriginalName($originalName);
        $picture->setType($extension);
        $picture->setTempPath('/temp/' . $filename);
        $picture->setGallery($gallery);
        $picture->setCreatedBy($security->getUser());

        $entityManager->persist($picture);
        $entityManager->flush();

        // 5. Dispatcher le message pour traitement async
        try {
            $messageBus->dispatch(new ProcessPictureMessage($picture->getId()));
        } catch (ExceptionInterface) {
            return $this->json(['success' => false, 'error' => "Echec de prise en charge de l'image"], 400);
        }

        // 6. Retourner la réponse
        return $this->json([
            'success' => true,
            'id' => $picture->getId(),
            'status' => Picture::STATUS_PROCESSING,
            'originalName' => $picture->getOriginalName(),
        ]);
    }
}
