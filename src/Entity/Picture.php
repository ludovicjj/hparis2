<?php

namespace App\Entity;

use App\Repository\PictureRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PictureRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Picture
{
    public const string STATUS_PROCESSING = 'processing';
    public const string STATUS_READY = 'ready';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $filename = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $originalName = null;

    #[ORM\Column(length: 10)]
    private ?string $type = null;

    #[ORM\Column(length: 20)]
    private ?string $status = null;

    #[ORM\Column]
    private ?int $position = 0;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'pictures')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Gallery $gallery = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $createdBy = null;

    /**
     * Temp path (original file, will be deleted after async processing)
     * @var string|null
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $tempPath = null;

    /**
     * Lightbox path (1200px)
     * @var string|null
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lightboxPath = null;

    /**
     * Thumbnail path (400px)
     * @var string|null
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $thumbnailPath = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new DateTimeImmutable();
        $this->status = self::STATUS_PROCESSING;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): static
    {
        $this->filename = $filename;

        return $this;
    }

    public function getOriginalName(): ?string
    {
        return $this->originalName;
    }

    public function setOriginalName(?string $originalName): static
    {
        $this->originalName = $originalName;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getGallery(): ?Gallery
    {
        return $this->gallery;
    }

    public function setGallery(?Gallery $gallery): static
    {
        $this->gallery = $gallery;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function getTempPath(): ?string
    {
        return $this->tempPath;
    }

    public function setTempPath(?string $tempPath): static
    {
        $this->tempPath = $tempPath;

        return $this;
    }

    public function getLightboxPath(): ?string
    {
        return $this->lightboxPath;
    }

    public function setLightboxPath(?string $lightboxPath): static
    {
        $this->lightboxPath = $lightboxPath;

        return $this;
    }

    public function getThumbnailPath(): ?string
    {
        return $this->thumbnailPath;
    }

    public function setThumbnailPath(?string $thumbnailPath): static
    {
        $this->thumbnailPath = $thumbnailPath;

        return $this;
    }
}
