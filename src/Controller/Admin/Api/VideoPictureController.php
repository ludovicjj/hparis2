<?php

namespace App\Controller\Admin\Api;

use App\Entity\Video;
use App\Entity\VideoPicture;
use App\Service\S3Service;
use App\Service\Video\VideoPictureService;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

#[Route('/admin/api', name: 'app_admin_api_')]
#[IsGranted('ROLE_ADMIN')]
class VideoPictureController extends AbstractController
{
    #[Route('/video/{id}/pictures', name: 'video_picture_upload', methods: ['POST'])]
    public function upload(
        Video $video,
        Request $request,
        VideoPictureService $service,
        S3Service $s3Service,
    ): JsonResponse {
        $file = $request->files->get('file');

        if ($file === null) {
            return $this->json(['error' => 'Aucun fichier fourni.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $videoPicture = $service->upload($video, $file);
        } catch (InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json([
            'id' => $videoPicture->getId(),
            'position' => $videoPicture->getPosition(),
            'thumbnailUrl' => $s3Service->getPublicUrl($videoPicture->getThumbnailPath()),
            'lightboxUrl' => $s3Service->getPublicUrl($videoPicture->getLightboxPath()),
        ]);
    }

    #[Route('/video-picture/{id}', name: 'video_picture_delete', methods: ['DELETE'])]
    public function delete(
        VideoPicture $videoPicture,
        Request $request,
        VideoPictureService $service,
    ): Response {
        $token = $request->headers->get('X-CSRF-Token') ?? $request->request->get('_token');

        if (!$this->isCsrfTokenValid('delete_video_picture' . $videoPicture->getId(), $token)) {
            return $this->json(['error' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        try {
            $service->delete($videoPicture);
        } catch (Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/video/{id}/pictures/reorder', name: 'video_picture_reorder', methods: ['POST'])]
    public function reorder(
        Video $video,
        Request $request,
        VideoPictureService $service,
    ): JsonResponse {
        try {
            $ids = $request->toArray()['ids'] ?? [];

            if (!is_array($ids)) {
                throw new InvalidArgumentException('Invalid input data, expected array.');
            }

            $service->reorder($video, $ids);

            return $this->json(['success' => true]);
        } catch (Throwable $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
