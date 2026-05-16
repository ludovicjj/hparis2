<?php

namespace App\Controller\Admin\Options;

use App\Entity\Option;
use App\Form\OptionType;
use App\Repository\OptionPictureRepository;
use App\Repository\OptionRepository;
use App\Repository\PageRepository;
use App\Service\JsonFormHandler;
use App\Service\Option\OptionPictureService;
use App\Service\Option\OptionService;
use App\Service\S3Service;
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
    public function index(OptionRepository $optionRepository): Response
    {
        return $this->render('admin/options/index.html.twig', [
            'options' => $optionRepository->findAllOrdered(),
            'optionCount' => $optionRepository->countAll(),
        ]);
    }

    #[Route('/create', name: 'create', methods: ['GET'])]
    public function create(): Response
    {
        $form = $this->createForm(OptionType::class, new Option());

        return $this->render('admin/options/create.html.twig', [
            'form' => $form,
            'maxPictures' => OptionPictureService::MAX_PICTURES_PER_OPTION,
        ]);
    }

    #[Route('/{id}/update', name: 'update', methods: ['GET'])]
    public function update(
        Option $option,
        OptionService $optionService,
        OptionPictureRepository $optionPictureRepository,
        S3Service $s3Service,
    ): Response {
        $form = $this->createForm(OptionType::class, $option);

        $optionPictures = array_map(
            fn ($picture) => [
                'id' => $picture->getId(),
                'thumbnailUrl' => $s3Service->getPublicUrl($picture->getThumbnailPath()),
            ],
            $optionPictureRepository->findByOptionOrdered($option),
        );

        return $this->render('admin/options/update.html.twig', [
            'option' => $option,
            'form' => $form,
            'front_option_url' => $optionService->generatePublicUrl($option),
            'optionPictures' => $optionPictures,
            'maxPictures' => OptionPictureService::MAX_PICTURES_PER_OPTION,
        ]);
    }

    #[Route('/create-stub', name: 'create_stub', methods: ['POST'])]
    public function createStub(
        Request $request,
        EntityManagerInterface $entityManager,
        OptionRepository $optionRepository,
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

        $option = new Option();
        $form = $this->createForm(OptionType::class, $option);

        if ($errorResponse = $formHandler->getValidationErrorResponse($form, $request)) {
            return $errorResponse;
        }

        $option->setPage($page);
        $option->setPosition($optionRepository->getNextPosition());

        $entityManager->persist($option);
        $entityManager->flush();

        $this->addFlash('success', 'Option ajoutée avec succès.');

        return $this->json([
            'id' => $option->getId(),
            'redirectUrl' => $this->generateUrl('app_admin_options_update', ['id' => $option->getId()]),
        ]);
    }

    #[Route('/{id}/update-stub', name: 'update_stub', methods: ['POST'])]
    public function updateStub(
        Request $request,
        Option $option,
        EntityManagerInterface $entityManager,
        JsonFormHandler $formHandler,
    ): JsonResponse {
        $form = $this->createForm(OptionType::class, $option);

        if ($errorResponse = $formHandler->getValidationErrorResponse($form, $request)) {
            return $errorResponse;
        }

        $entityManager->flush();

        $this->addFlash('success', 'Option modifiée avec succès.');

        return $this->json([
            'id' => $option->getId(),
            'redirectUrl' => $this->generateUrl('app_admin_options_index'),
        ]);
    }

    #[Route('/{id}/token', name: 'token', methods: ['POST'])]
    public function resetToken(
        Option $option,
        EntityManagerInterface $entityManager,
        OptionService $optionService,
    ): Response {
        $option->resetToken();
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'url' => $optionService->generatePublicUrl($option),
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Option $option,
        EntityManagerInterface $entityManager,
        OptionPictureService $optionPictureService,
    ): Response {
        if ($this->isCsrfTokenValid('delete' . $option->getId(), $request->request->get('_token'))) {
            $optionPictureService->cleanupFilesForOption($option);
            $entityManager->remove($option);
            $entityManager->flush();

            $this->addFlash('success', 'Option supprimée avec succès.');
        }

        return $this->redirectToRoute('app_admin_options_index');
    }

    #[Route('/{id}/toggle', name: 'toggle', methods: ['POST'])]
    public function toggle(
        Request $request,
        Option $option,
        EntityManagerInterface $entityManager,
    ): Response {
        if ($this->isCsrfTokenValid('toggle' . $option->getId(), $request->request->get('_token'))) {
            $option->setActive(!$option->isActive());
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_admin_options_index');
    }

    #[Route('/reorder', name: 'reorder', methods: ['POST'])]
    public function reorder(
        Request $request,
        OptionRepository $optionRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        try {
            $ids = $request->toArray()['ids'] ?? [];

            if (!is_array($ids)) {
                throw new InvalidArgumentException('Invalid input data, expected array.');
            }

            $options = $optionRepository->findBy(['id' => $ids]);
            $indexed = [];
            foreach ($options as $option) {
                $indexed[$option->getId()] = $option;
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
