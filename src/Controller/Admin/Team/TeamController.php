<?php

namespace App\Controller\Admin\Team;

use App\Entity\Video;
use App\Form\VideoType;
use App\Repository\PageRepository;
use App\Repository\VideoPictureRepository;
use App\Repository\VideoRepository;
use App\Service\JsonFormHandler;
use App\Service\S3Service;
use App\Service\Video\VideoPictureService;
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

#[Route('/admin/team', name: 'app_admin_team_')]
#[IsGranted('ROLE_ADMIN')]
class TeamController extends AbstractController
{
    private const string PAGE_SLUG = 'team';

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(VideoRepository $videoRepository): Response
    {
        return $this->render('admin/team/index.html.twig', [
            'videos' => $videoRepository->findAllOrderedByPageSlug(self::PAGE_SLUG),
            'videoCount' => $videoRepository->countByPageSlug(self::PAGE_SLUG),
        ]);
    }

    #[Route('/create', name: 'create', methods: ['GET'])]
    public function create(): Response
    {
        $form = $this->createForm(VideoType::class, new Video());

        return $this->render('admin/team/create.html.twig', [
            'form' => $form,
            'maxPictures' => VideoPictureService::MAX_PICTURES_PER_VIDEO,
        ]);
    }

    #[Route('/{id}/update', name: 'update', methods: ['GET'])]
    public function update(
        Video $video,
        VideoService $videoService,
        VideoPictureRepository $videoPictureRepository,
        S3Service $s3Service,
    ): Response {
        $form = $this->createForm(VideoType::class, $video);

        $videoPictures = array_map(
            fn($videoPicture) => [
                'id' => $videoPicture->getId(),
                'thumbnailUrl' => $s3Service->getPublicUrl($videoPicture->getThumbnailPath()),
            ],
            $videoPictureRepository->findByVideoOrdered($video),
        );

        return $this->render('admin/team/update.html.twig', [
            'video' => $video,
            'form' => $form,
            'front_video_url' => $videoService->generatePublicUrl($video),
            'videoPictures' => $videoPictures,
            'maxPictures' => VideoPictureService::MAX_PICTURES_PER_VIDEO,
        ]);
    }

    #[Route('/create-stub', name: 'create_stub', methods: ['POST'])]
    public function createStub(
        Request $request,
        EntityManagerInterface $entityManager,
        VideoRepository $videoRepository,
        PageRepository $pageRepository,
        JsonFormHandler $formHandler,
    ): JsonResponse {
        $page = $pageRepository->findOneBySlug(self::PAGE_SLUG);
        if ($page === null) {
            return $this->json(
                ['error' => sprintf('Page "%s" not seeded. Run app:seed-pages.', self::PAGE_SLUG)],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        $video = new Video();
        $form = $this->createForm(VideoType::class, $video);

        if ($errorResponse = $formHandler->getValidationErrorResponse($form, $request)) {
            return $errorResponse;
        }

        $video->setPage($page);
        $video->setPosition($videoRepository->getNextPosition());

        $entityManager->persist($video);
        $entityManager->flush();

        $this->addFlash('success', 'Vidéo ajoutée avec succès.');

        return $this->json([
            'id' => $video->getId(),
            'redirectUrl' => $this->generateUrl('app_admin_team_update', ['id' => $video->getId()]),
        ]);
    }

    #[Route('/{id}/update-stub', name: 'update_stub', methods: ['POST'])]
    public function updateStub(
        Request $request,
        Video $video,
        EntityManagerInterface $entityManager,
        JsonFormHandler $formHandler,
    ): JsonResponse {
        $form = $this->createForm(VideoType::class, $video);

        if ($errorResponse = $formHandler->getValidationErrorResponse($form, $request)) {
            return $errorResponse;
        }

        $entityManager->flush();

        $this->addFlash('success', 'Vidéo modifiée avec succès.');

        return $this->json([
            'id' => $video->getId(),
            'redirectUrl' => $this->generateUrl('app_admin_team_index'),
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
        VideoPictureService $videoPictureService,
    ): Response {
        if ($this->isCsrfTokenValid('delete' . $video->getId(), $request->request->get('_token'))) {
            $videoPictureService->cleanupFilesForVideo($video);
            $entityManager->remove($video);
            $entityManager->flush();

            $this->addFlash('success', 'Vidéo supprimée avec succès.');
        }

        return $this->redirectToRoute('app_admin_team_index');
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

        return $this->redirectToRoute('app_admin_team_index');
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
