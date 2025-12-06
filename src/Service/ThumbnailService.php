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
        private string $uploadDirectory,
    ) {
    }

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
            $this->delete($thumbnail);
        } else {
            $thumbnail = new Thumbnail();
            $gallery->setThumbnail($thumbnail);
        }

        $thumbnail->setFilename($filename);
        $thumbnail->setOriginalName($originalName);
        $thumbnail->setType($extension);

        $thumbnailDir = $this->uploadDirectory . '/thumbnails';
        $this->filesystem->mkdir($thumbnailDir);

        $file->move($thumbnailDir, $filename);
    }

    public function delete(Thumbnail $thumbnail): void
    {
        $path = $this->uploadDirectory . '/thumbnails/' . $thumbnail->getFilename();

        if ($this->filesystem->exists($path)) {
            $this->filesystem->remove($path);
        }
    }
}
