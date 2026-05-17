<?php

namespace App\Controller\Admin\Team;

use App\Entity\Team;
use App\Form\TeamType;
use App\Repository\TeamPictureRepository;
use App\Repository\TeamRepository;
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
    public function create(
        TeamRepository $teamRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $draft = $teamRepository->findOneBy(['isDraft' => true]);

        if ($draft === null) {
            $draft = new Team()
                ->setIsDraft(true)
                ->setVisibility(false)
                ->setPosition($teamRepository->getNextPosition());

            $entityManager->persist($draft);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_admin_team_update', ['id' => $draft->getId()]);
    }

    #[Route('/{id}/update', name: 'update', methods: ['GET', 'POST'])]
    public function update(
        Request $request,
        Team $team,
        EntityManagerInterface $entityManager,
        TeamService $teamService,
        TeamPictureRepository $teamPictureRepository,
        S3Service $s3Service,
    ): Response {
        $form = $this->createForm(TeamType::class, $team);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $wasDraft = $team->isDraft();
            if ($wasDraft) {
                $team->setIsDraft(false);
            }

            $entityManager->flush();

            $this->addFlash('success', $wasDraft ? 'Team ajoutée avec succès.' : 'Team modifiée avec succès.');

            return $this->redirectToRoute('app_admin_team_index');
        }

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
