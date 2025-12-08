<?php

namespace App\Controller\admin\Api;

use App\Entity\Picture;
use App\Service\PictureService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Exception;

#[Route('/admin/api/picture', name: 'api_picture_')]
#[IsGranted('ROLE_ADMIN')]
class PictureController extends AbstractController
{
    public function __construct(
        private readonly PictureService $pictureService,
    ) {
    }

    #[Route('/upload', name: 'upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        $error = $this->pictureService->validate($file);
        if ($error) {
            return $this->json(['success' => false, 'error' => $error], 400);
        }

        try {
            $picture = $this->pictureService->upload($file);
        } catch (Exception $e) {
            return $this->json([
                'success' => false,
                'error' => "Erreur lors de l'upload: " . $e->getMessage()
            ], 500);
        }

        return $this->json([
            'success' => true,
            'id' => $picture->getId(),
            'path' => $picture->getThumbnailPath(),
            'originalName' => $picture->getOriginalName(),
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Picture $picture): JsonResponse
    {
        $this->pictureService->delete($picture);

        return $this->json(['success' => true]);
    }
}
