<?php

namespace App\Entity;

use App\Repository\GalleryRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: GalleryRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Gallery
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?DateTimeImmutable $updatedAt = null;

    #[ORM\OneToOne(targetEntity: Thumbnail::class, mappedBy: 'gallery', cascade: ['persist', 'remove'])]
    private ?Thumbnail $thumbnail = null;

    /** @var Collection<int, Picture> */
    #[ORM\OneToMany(targetEntity: Picture::class, mappedBy: 'gallery', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $pictures;

    #[ORM\Column(options: ['default' => true])]
    private bool $visibility;

    #[ORM\Column(nullable: true)]
    private ?string $token = null;

    public function __construct()
    {
        $this->pictures = new ArrayCollection();
        $this->visibility = true;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->token = $this->generateToken();
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

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

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /** @return Collection<int, Picture> */
    public function getPictures(): Collection
    {
        return $this->pictures;
    }

    public function addPicture(Picture $picture): static
    {
        if (!$this->pictures->contains($picture)) {
            $this->pictures->add($picture);
            $picture->setGallery($this);
        }

        return $this;
    }

    public function removePicture(Picture $picture): static
    {
        if ($this->pictures->removeElement($picture)) {
            if ($picture->getGallery() === $this) {
                $picture->setGallery(null);
            }
        }

        return $this;
    }

    public function getThumbnail(): ?Thumbnail
    {
        return $this->thumbnail;
    }

    public function setThumbnail(?Thumbnail $thumbnail): static
    {
        if ($thumbnail !== null && $thumbnail->getGallery() !== $this) {
            $thumbnail->setGallery($this);
        }

        $this->thumbnail = $thumbnail;

        return $this;
    }

    public function setVisibility(bool $visibility): static
    {
        $this->visibility = $visibility;
        return $this;
    }

    public function isVisibility(): bool
    {
        return $this->visibility;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(?string $token): static
    {
        $this->token = $token;

        return $this;
    }

    private function generateToken(): string {
        try {
            return bin2hex(random_bytes(32));
        } catch (Exception $e) {
            // fallback openssl
            return bin2hex(openssl_random_pseudo_bytes(32));
        }
    }

    public function resetToken(): void
    {
        $this->token = $this->generateToken();
    }
}
