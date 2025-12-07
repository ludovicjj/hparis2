<?php

namespace App\Controller\admin;

use App\Entity\Gallery;
use App\Entity\Picture;
use App\Repository\GalleryRepository;
use App\Repository\PictureRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route(path: '/admin/dashboard', name: 'app_admin_dashboard')]
    public function index(
        GalleryRepository $galleryRepository,
        PictureRepository $pictureRepository
    ): Response {
        $galleryCount = $galleryRepository->countAll();
        $pictureCount = $pictureRepository->countByStatus(Picture::STATUS_ATTACHED);

        return $this->render('admin/dashboard/index.html.twig', [
            'galleryCount' => $galleryCount,
            'pictureCount' => $pictureCount,
        ]);
    }
}