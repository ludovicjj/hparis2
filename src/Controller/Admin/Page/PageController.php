<?php

namespace App\Controller\Admin\Page;

use App\Entity\Page;
use App\Form\PageType;
use App\Repository\PageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/page', name: 'app_admin_page_')]
class PageController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(PageRepository $pageRepository): Response
    {
        return $this->render('admin/page/index.html.twig', [
            'pages' => $pageRepository->findAllOrdered(),
        ]);
    }

    #[Route('/{id}/update', name: 'update', methods: ['GET', 'POST'])]
    public function update(
        Request $request,
        Page $page,
        EntityManagerInterface $entityManager,
    ): Response {
        $form = $this->createForm(PageType::class, $page);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Page modifiée avec succès.');

            return $this->redirectToRoute('app_admin_page_index');
        }

        return $this->render('admin/page/update.html.twig', [
            'page' => $page,
            'form' => $form,
        ]);
    }
}
