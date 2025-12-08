<?php

namespace App\Service;

use App\Entity\Gallery;
use App\Entity\Thumbnail;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

readonly class ThumbnailService
{
    public function __construct(
        private SluggerInterface $slugger,
        private Filesystem $filesystem,
        private ImageOptimizerService $imageOptimizerService,
        private string $uploadDirectory,
    ) {
    }

    /**
     * Handle Thumbnail File when user create or update one gallery
     *
     * @param FormInterface $form
     * @return void
     */
    public function handle(FormInterface $form): void
    {
        /** @var Gallery $gallery */
        $gallery = $form->getData();
        /** @var ?UploadedFile $file */
        $file = $form->get('thumbnailFile')->getData();

        if (!$file) {
            return;
        }

        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = strtolower($file->guessExtension() ?? $file->getClientOriginalExtension());
        $safeFilename = $this->slugger->slug($originalName);
        $filename = $safeFilename . '-' . uniqid() . '.' . $extension;

        $thumbnail = $gallery->getThumbnail();
        if ($thumbnail) {
            // Clear odl thumbnail file
            $this->deleteFile($thumbnail);
        } else {
            $thumbnail = new Thumbnail();
            $gallery->setThumbnail($thumbnail);
        }

        $thumbnail->setFilename($filename);
        $thumbnail->setOriginalName($originalName);
        $thumbnail->setType($extension);

        $thumbnailDir = $this->uploadDirectory . '/thumbnails';
        $this->filesystem->mkdir($thumbnailDir);

        // Create and Upload Thumbnail file (600px)
        $this->imageOptimizerService->optimizeThumbnail(
            $file->getPathname(),
            $thumbnailDir,
            $filename,
            ImageOptimizerService::MEDIUM_WITH
        );
    }

    /**
     * Delete only the file (not the entity)
     */
    public function deleteFile(Thumbnail $thumbnail): void
    {
        $path = $this->uploadDirectory . '/thumbnails/' . $thumbnail->getFilename();

        if ($this->filesystem->exists($path)) {
            $this->filesystem->remove($path);
        }
    }
}
