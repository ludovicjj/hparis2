<?php

namespace App\Controller\Admin\Team;

use App\Entity\Team;
use App\Form\TeamType;
use App\Repository\PageRepository;
use App\Repository\TeamPictureRepository;
use App\Repository\TeamRepository;
use App\Service\JsonFormHandler;
use App\Service\S3Service;
use App\Service\Team\TeamPictureService;
use App\Service\Team\TeamService;
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
    public function index(TeamRepository $teamRepository): Response
    {
        return $this->render('admin/team/index.html.twig', [
            'teams' => $teamRepository->findAllOrdered(),
            'teamCount' => $teamRepository->countAll(),
        ]);
    }

    #[Route('/create', name: 'create', methods: ['GET'])]
    public function create(): Response
    {
        $form = $this->createForm(TeamType::class, new Team());

        return $this->render('admin/team/create.html.twig', [
            'form' => $form,
            'maxPictures' => TeamPictureService::MAX_PICTURES_PER_TEAM,
        ]);
    }

    #[Route('/{id}/update', name: 'update', methods: ['GET'])]
    public function update(
        Team $team,
        TeamService $teamService,
        TeamPictureRepository $teamPictureRepository,
        S3Service $s3Service,
    ): Response {
        $form = $this->createForm(TeamType::class, $team);

        $teamPictures = array_map(
            fn ($picture) => [
                'id' => $picture->getId(),
                'thumbnailUrl' => $s3Service->getPublicUrl($picture->getThumbnailPath()),
            ],
            $teamPictureRepository->findByTeamOrdered($team),
        );

        return $this->render('admin/team/update.html.twig', [
            'team' => $team,
            'form' => $form,
            'front_team_url' => $teamService->generatePublicUrl($team),
            'teamPictures' => $teamPictures,
            'maxPictures' => TeamPictureService::MAX_PICTURES_PER_TEAM,
        ]);
    }

    #[Route('/create-stub', name: 'create_stub', methods: ['POST'])]
    public function createStub(
        Request $request,
        EntityManagerInterface $entityManager,
        TeamRepository $teamRepository,
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

        $team = new Team();
        $form = $this->createForm(TeamType::class, $team);

        if ($errorResponse = $formHandler->getValidationErrorResponse($form, $request)) {
            return $errorResponse;
        }

        $team->setPage($page);
        $team->setPosition($teamRepository->getNextPosition());

        $entityManager->persist($team);
        $entityManager->flush();

        $this->addFlash('success', 'Team ajoutée avec succès.');

        return $this->json([
            'id' => $team->getId(),
            'redirectUrl' => $this->generateUrl('app_admin_team_update', ['id' => $team->getId()]),
        ]);
    }

    #[Route('/{id}/update-stub', name: 'update_stub', methods: ['POST'])]
    public function updateStub(
        Request $request,
        Team $team,
        EntityManagerInterface $entityManager,
        JsonFormHandler $formHandler,
    ): JsonResponse {
        $form = $this->createForm(TeamType::class, $team);

        if ($errorResponse = $formHandler->getValidationErrorResponse($form, $request)) {
            return $errorResponse;
        }

        $entityManager->flush();

        $this->addFlash('success', 'Team modifiée avec succès.');

        return $this->json([
            'id' => $team->getId(),
            'redirectUrl' => $this->generateUrl('app_admin_team_index'),
        ]);
    }

    #[Route('/{id}/token', name: 'token', methods: ['POST'])]
    public function resetToken(
        Team $team,
        EntityManagerInterface $entityManager,
        TeamService $teamService,
    ): Response {
        $team->resetToken();
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'url' => $teamService->generatePublicUrl($team),
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Team $team,
        EntityManagerInterface $entityManager,
        TeamPictureService $teamPictureService,
    ): Response {
        if ($this->isCsrfTokenValid('delete' . $team->getId(), $request->request->get('_token'))) {
            $teamPictureService->cleanupFilesForTeam($team);
            $entityManager->remove($team);
            $entityManager->flush();

            $this->addFlash('success', 'Team supprimée avec succès.');
        }

        return $this->redirectToRoute('app_admin_team_index');
    }

    #[Route('/{id}/toggle', name: 'toggle', methods: ['POST'])]
    public function toggle(
        Request $request,
        Team $team,
        EntityManagerInterface $entityManager,
    ): Response {
        if ($this->isCsrfTokenValid('toggle' . $team->getId(), $request->request->get('_token'))) {
            $team->setActive(!$team->isActive());
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_admin_team_index');
    }

    #[Route('/reorder', name: 'reorder', methods: ['POST'])]
    public function reorder(
        Request $request,
        TeamRepository $teamRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        try {
            $ids = $request->toArray()['ids'] ?? [];

            if (!is_array($ids)) {
                throw new InvalidArgumentException('Invalid input data, expected array.');
            }

            $teams = $teamRepository->findBy(['id' => $ids]);
            $indexed = [];
            foreach ($teams as $team) {
                $indexed[$team->getId()] = $team;
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
