<?php

namespace App\Controller\Admin\Setting;

use App\Entity\SocialLink;
use App\Enum\MediaSetting;
use App\Form\HeroType;
use App\Form\LogoType;
use App\Form\SocialLinkType;
use App\Repository\SocialLinkRepository;
use App\Service\MediaSettingService;
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
    public function index(SocialLinkRepository $repository, MediaSettingService $mediaSettingService): Response
    {
        $logoForm = $this->createForm(LogoType::class, null, [
            'action' => $this->generateUrl('app_admin_settings_logo_update'),
        ]);

        $heroForm = $this->createForm(HeroType::class, null, [
            'action' => $this->generateUrl('app_admin_settings_hero_update'),
        ]);

        return $this->render('admin/settings/index.html.twig', [
            'socialLinks' => $repository->findAllOrdered(),
            'logoForm' => $logoForm,
            'logoUrl' => $mediaSettingService->getPublicUrl(MediaSetting::LOGO),
            'heroForm' => $heroForm,
            'heroUrl' => $mediaSettingService->getPublicUrl(MediaSetting::HERO),
        ]);
    }

    #[Route('/logo', name: '_logo_update', methods: ['POST'])]
    public function updateLogo(Request $request, MediaSettingService $mediaSettingService): Response
    {
        $form = $this->createForm(LogoType::class)->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $mediaSettingService->handle($form, MediaSetting::LOGO);
                $this->addFlash('success', 'Logo mis à jour.');
            } catch (Throwable $e) {
                $this->addFlash('error', 'Erreur lors de la mise à jour du logo : ' . $e->getMessage());
            }
        } else {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        return $this->redirectToRoute('app_admin_settings');
    }

    #[Route('/hero', name: '_hero_update', methods: ['POST'])]
    public function updateHero(Request $request, MediaSettingService $mediaSettingService): Response
    {
        $form = $this->createForm(HeroType::class)->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $mediaSettingService->handle($form, MediaSetting::HERO);
                $this->addFlash('success', 'Hero mis à jour.');
            } catch (Throwable $e) {
                $this->addFlash('error', 'Erreur lors de la mise à jour du hero : ' . $e->getMessage());
            }
        } else {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        return $this->redirectToRoute('app_admin_settings');
    }

    #[Route('/logo/delete', name: '_logo_delete', methods: ['POST'])]
    public function deleteLogo(Request $request, MediaSettingService $mediaSettingService): Response
    {
        if (!$this->isCsrfTokenValid('delete_logo', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_settings');
        }

        try {
            $mediaSettingService->delete(MediaSetting::LOGO);
            $this->addFlash('success', 'Logo supprimé.');
        } catch (Throwable $e) {
            $this->addFlash('error', 'Erreur lors de la suppression du logo : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_settings');
    }

    #[Route('/hero/delete', name: '_hero_delete', methods: ['POST'])]
    public function deleteHero(Request $request, MediaSettingService $mediaSettingService): Response
    {
        if (!$this->isCsrfTokenValid('delete_hero', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_settings');
        }

        try {
            $mediaSettingService->delete(MediaSetting::HERO);
            $this->addFlash('success', 'Hero supprimé.');
        } catch (Throwable $e) {
            $this->addFlash('error', 'Erreur lors de la suppression du hero : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_settings');
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
