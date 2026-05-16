<?php

namespace App\Controller\Admin\Options;

use App\Entity\Video;
use App\Form\VideoType;
use App\Repository\PageRepository;
use App\Repository\VideoRepository;
use App\Service\Video\VideoService;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

#[Route('/admin/options', name: 'app_admin_options_')]
#[IsGranted('ROLE_ADMIN')]
class OptionsController extends AbstractController
{
    private const string PAGE_SLUG = 'options';

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(VideoRepository $videoRepository): Response
    {
        return $this->render('admin/options/index.html.twig', [
            'videos' => $videoRepository->findAllOrderedByPageSlug(self::PAGE_SLUG),
            'videoCount' => $videoRepository->countByPageSlug(self::PAGE_SLUG),
        ]);
    }

    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        VideoRepository $videoRepository,
        PageRepository $pageRepository,
    ): Response {
        $page = $pageRepository->findOneBySlug(self::PAGE_SLUG);
        if ($page === null) {
            throw $this->createNotFoundException(sprintf('Page "%s" not seeded. Run app:seed-pages.', self::PAGE_SLUG));
        }

        $video = new Video();
        $form = $this->createForm(VideoType::class, $video);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $video->setPage($page);
            $video->setPosition($videoRepository->getNextPosition());

            $entityManager->persist($video);
            $entityManager->flush();

            $this->addFlash('success', 'Vidéo ajoutée avec succès.');

            return $this->redirectToRoute('app_admin_options_index');
        }

        return $this->render('admin/options/create.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/update', name: 'update', methods: ['GET', 'POST'])]
    public function update(
        Request $request,
        Video $video,
        EntityManagerInterface $entityManager,
        VideoService $videoService,
    ): Response {
        $form = $this->createForm(VideoType::class, $video);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Vidéo modifiée avec succès.');

            return $this->redirectToRoute('app_admin_options_index');
        }

        return $this->render('admin/options/update.html.twig', [
            'video' => $video,
            'form' => $form,
            'front_video_url' => $videoService->generatePublicUrl($video),
        ]);
    }

    #[Route('/{id}/token', name: 'token', methods: ['POST'])]
    public function resetToken(
        Video $video,
        EntityManagerInterface $entityManager,
        VideoService $videoService,
    ): Response {
        $video->resetToken();
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'url' => $videoService->generatePublicUrl($video),
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Video $video,
        EntityManagerInterface $entityManager,
    ): Response {
        if ($this->isCsrfTokenValid('delete' . $video->getId(), $request->request->get('_token'))) {
            $entityManager->remove($video);
            $entityManager->flush();

            $this->addFlash('success', 'Vidéo supprimée avec succès.');
        }

        return $this->redirectToRoute('app_admin_options_index');
    }

    #[Route('/{id}/toggle', name: 'toggle', methods: ['POST'])]
    public function toggle(
        Request $request,
        Video $video,
        EntityManagerInterface $entityManager,
    ): Response {
        if ($this->isCsrfTokenValid('toggle' . $video->getId(), $request->request->get('_token'))) {
            $video->setActive(!$video->isActive());
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_admin_options_index');
    }

    #[Route('/reorder', name: 'reorder', methods: ['POST'])]
    public function reorder(
        Request $request,
        VideoRepository $videoRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        try {
            $ids = $request->toArray()['ids'] ?? [];

            if (!is_array($ids)) {
                throw new InvalidArgumentException('Invalid input data, expected array.');
            }

            $videos = $videoRepository->findBy(['id' => $ids]);
            $indexed = [];
            foreach ($videos as $video) {
                $indexed[$video->getId()] = $video;
            }

            foreach ($ids as $position => $id) {
                if (isset($indexed[$id])) {
                    $indexed[$id]->setPosition($position);
                }
            }

            $entityManager->flush();

            return $this->json(['success' => true]);
        } catch (Throwable $exception) {
            return $this->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
