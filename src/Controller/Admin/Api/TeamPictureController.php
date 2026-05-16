<?php

namespace App\Controller\Admin\Api;

use App\Entity\Team;
use App\Entity\TeamPicture;
use App\Service\S3Service;
use App\Service\Team\TeamPictureService;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

#[Route('/admin/api', name: 'app_admin_api_team_picture_')]
#[IsGranted('ROLE_ADMIN')]
class TeamPictureController extends AbstractController
{
    #[Route('/team/{id}/pictures', name: 'upload', methods: ['POST'])]
    public function upload(
        Team $team,
        Request $request,
        TeamPictureService $service,
        S3Service $s3Service,
    ): JsonResponse {
        $file = $request->files->get('file');

        if ($file === null) {
            return $this->json(['error' => 'Aucun fichier fourni.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $picture = $service->upload($team, $file);
        } catch (InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json([
            'id' => $picture->getId(),
            'position' => $picture->getPosition(),
            'thumbnailUrl' => $s3Service->getPublicUrl($picture->getThumbnailPath()),
            'lightboxUrl' => $s3Service->getPublicUrl($picture->getLightboxPath()),
        ]);
    }

    #[Route('/team-picture/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(
        TeamPicture $picture,
        Request $request,
        TeamPictureService $service,
    ): Response {
        $token = $request->headers->get('X-CSRF-Token') ?? $request->request->get('_token');

        if (!$this->isCsrfTokenValid('delete_team_picture' . $picture->getId(), $token)) {
            return $this->json(['error' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        try {
            $service->delete($picture);
        } catch (Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/team/{id}/pictures/reorder', name: 'reorder', methods: ['POST'])]
    public function reorder(
        Team $team,
        Request $request,
        TeamPictureService $service,
    ): JsonResponse {
        try {
            $ids = $request->toArray()['ids'] ?? [];

            if (!is_array($ids)) {
                throw new InvalidArgumentException('Invalid input data, expected array.');
            }

            $service->reorder($team, $ids);

            return $this->json(['success' => true]);
        } catch (Throwable $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
