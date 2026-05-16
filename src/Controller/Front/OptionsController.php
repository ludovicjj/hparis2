<?php

namespace App\Controller\Front;

use App\Entity\Option;
use App\Repository\OptionPictureRepository;
use App\Repository\OptionRepository;
use App\Service\Option\OptionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/options', name: 'app_front_options_')]
class OptionsController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        OptionRepository $optionRepository,
        OptionPictureRepository $optionPictureRepository,
    ): Response {
        $options = $optionRepository->findPublicActive();
        $ids = array_map(fn (Option $option) => $option->getId(), $options);
        $picturesByOptionId = $optionPictureRepository->findGroupedByOptionIds($ids);

        return $this->render('front/options/index.html.twig', [
            'options' => $options,
            'picturesByOptionId' => $picturesByOptionId,
        ]);
    }

    #[Route('/{id<\d+>}', name: 'show', methods: ['GET'])]
    public function show(
        Option $option,
        Request $request,
        OptionService $optionService,
        OptionPictureRepository $optionPictureRepository,
    ): Response {
        if (!$optionService->canAccessOption($option, $request->query->get('token'))) {
            return $this->redirectToRoute('app_front_options_index');
        }

        return $this->render('front/options/show.html.twig', [
            'option' => $option,
            'token' => $request->query->get('token'),
            'pictures' => $optionPictureRepository->findByOptionOrdered($option),
        ]);
    }
}
