<?php

namespace App\Controller\Admin\Setting;

use App\Entity\SocialLink;
use App\Form\SocialLinkType;
use App\Repository\SocialLinkRepository;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

#[Route('/admin/settings', name: 'app_admin_settings')]
class SettingsController extends AbstractController
{
    #[Route('', name: '', methods: ['GET'])]
    public function index(SocialLinkRepository $repository): Response
    {
        return $this->render('admin/settings/index.html.twig', [
            'socialLinks' => $repository->findAllOrdered(),
        ]);
    }

    #[Route('/social-link/create', name: '_social_link_create', methods: ['GET', 'POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        SocialLinkRepository $repository
    ): Response {
        $socialLink = new SocialLink();

        $form = $this->createForm(SocialLinkType::class, $socialLink);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $socialLink->setPosition($repository->getNextPosition());
            $em->persist($socialLink);
            $em->flush();

            $this->addFlash('success', 'Réseau social ajouté.');

            return $this->redirectToRoute('app_admin_settings');
        }

        return $this->render('admin/settings/social_link_form.html.twig', [
            'form' => $form,
            'isEdit' => false,
        ]);
    }

    #[Route('/social-link/{id}/edit', name: '_social_link_edit', methods: ['GET', 'POST'])]
    public function update(
        SocialLink $socialLink,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $form = $this->createForm(SocialLinkType::class, $socialLink);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Réseau social modifié.');

            return $this->redirectToRoute('app_admin_settings');
        }

        return $this->render('admin/settings/social_link_form.html.twig', [
            'form' => $form,
            'isEdit' => true,
        ]);
    }

    #[Route('/social-link/{id}/delete', name: '_social_link_delete', methods: ['POST'])]
    public function deleteSocialLink(
        SocialLink $socialLink,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $csrfInputToken = $request->request->get('_token');

        if ($this->isCsrfTokenValid('delete' . $socialLink->getId(), $csrfInputToken)) {
            $em->remove($socialLink);
            $em->flush();

            $this->addFlash('success', 'Réseau social supprimé.');
        }

        return $this->redirectToRoute('app_admin_settings');
    }

    #[Route('/social-link/{id}/toggle', name: '_social_link_toggle', methods: ['POST'])]
    public function toggleSocialLink(
        SocialLink $socialLink,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $csrfInputToken = $request->request->get('_token');

        if ($this->isCsrfTokenValid('toggle' . $socialLink->getId(), $csrfInputToken)) {
            $socialLink->setActive(!$socialLink->isActive());
            $em->flush();
        }

        return $this->redirectToRoute('app_admin_settings');
    }

    #[Route('/social-link/reorder', name: '_social_link_reorder', methods: ['POST'])]
    public function reorderSocialLinks(
        Request $request,
        SocialLinkRepository $repository,
        EntityManagerInterface $em
    ): JsonResponse {
        try {
            $ids = $request->toArray()['ids'] ?? [];

            if (!is_array($ids)) {
                throw new InvalidArgumentException('Invalid input data, expected array.');
            }

            $socialLinks = $repository->findBy(['id' => $ids]);
            $indexed = [];
            foreach ($socialLinks as $link) {
                $indexed[$link->getId()] = $link;
            }

            foreach ($ids as $position => $id) {
                if (isset($indexed[$id])) {
                    $indexed[$id]->setPosition($position);
                }
            }

            $em->flush();

            return $this->json(['success' => true]);
        } catch (Throwable $exception) {
            return $this->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

    }
}
