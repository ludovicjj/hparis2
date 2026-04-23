<?php

namespace App\Entity;

use App\Repository\GalleryCategoryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GalleryCategoryRepository::class)]
#[ORM\Table(name: 'gallery_category')]
class GalleryCategory
{
    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'galleryCategories')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Gallery $gallery;

    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'galleryCategories')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Category $category;

    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    public function __construct(Gallery $gallery, Category $category)
    {
        $this->gallery = $gallery;
        $this->category = $category;
    }

    public function getGallery(): Gallery
    {
        return $this->gallery;
    }

    public function getCategory(): Category
    {
        return $this->category;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;
        return $this;
    }
}
