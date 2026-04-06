<?php

namespace App\Service;

use App\Entity\Gallery;
use App\Entity\Thumbnail;
use RuntimeException;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

readonly class ThumbnailService
{
    public function __construct(
        private SluggerInterface $slugger,
        private ImageOptimizerService $imageOptimizerService,
        private S3Service $s3Service,
    ) {
    }

    /**
     * Handle the cover (Thumbnail) of a gallery on create or update.
     *
     * IMPORTANT: the gallery MUST already have an ID when this is called, because
     * the S3 key embeds the gallery id (galleries/{id}/cover/...). On the create
     * flow, the controller must persist+flush the gallery first, then call this.
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

        if ($gallery->getId() === null) {
            throw new RuntimeException(
                'Gallery must be flushed before ThumbnailService::handle().'
            );
        }

        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalName);

        // init S3 key
        $key = sprintf(
            'galleries/%d/cover/%s-%s.jpg',
            $gallery->getId(),
            $safeFilename,
            uniqid()
        );

        // Resize to 600px in memory, then upload to S3 as a public-readable JPEG.
        $resizedBinary = $this->imageOptimizerService->optimizeThumbnail(
            $file->getContent(),
            ImageOptimizerService::MEDIUM_WITH
        );

        if (!$this->s3Service->uploadPublicFile($key, $resizedBinary, 'image/jpeg')) {
            throw new RuntimeException('Failed to upload gallery cover to object storage.');
        }

        $thumbnail = $gallery->getThumbnail();

        // Clear S3
        if ($thumbnail) {
            $this->deleteFile($thumbnail);
        } else {
            $thumbnail = new Thumbnail();
            $gallery->setThumbnail($thumbnail);
        }

        $thumbnail->setFilename($key);
        $thumbnail->setOriginalName($originalName);
        $thumbnail->setType('jpg');
    }

    /**
     * Delete the cover object from S3 (does not touch the entity).
     */
    public function deleteFile(Thumbnail $thumbnail): void
    {
        $key = $thumbnail->getFilename();

        if ($key) {
            $this->s3Service->deleteFile($key);
        }
    }
}
