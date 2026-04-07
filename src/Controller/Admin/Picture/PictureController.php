<?php

namespace App\Controller\Admin\Picture;

use App\Entity\Picture;
use App\Message\ProcessPictureMessage;
use App\Service\PictureService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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

    #[Route('/{id}/uploaded', name: 'uploaded', methods: ['POST'])]
    public function confirmUploaded(
        Picture $picture,
        MessageBusInterface $messageBus,
    ): JsonResponse {
        if ($picture->getStatus() !== Picture::STATUS_PROCESSING) {
            return $this->json(['success' => false, 'error' => "L'image n'est pas en attente de traitement"], 400);
        }

        try {
            $messageBus->dispatch(new ProcessPictureMessage($picture->getId()));
        } catch (ExceptionInterface) {
            return $this->json(['success' => false, 'error' => "Echec de prise en charge de l'image"], 400);
        }

        return $this->json(['success' => true]);
    }
}
