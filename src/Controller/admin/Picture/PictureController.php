<?php

namespace App\Controller\admin\Picture;

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

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Picture $picture): JsonResponse
    {
        $this->pictureService->delete($picture);

        return $this->json(['success' => true]);
    }
}
