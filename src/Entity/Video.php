<?php

namespace App\Entity;

use App\Enum\VideoProvider;
use App\Repository\VideoRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: VideoRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Video
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Assert\Length(max: 255)]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[Assert\NotBlank(message: 'Le lien de la vidéo est obligatoire.')]
    #[Assert\Url(message: "Cette URL n'est pas valide.")]
    #[Assert\Length(max: 500)]
    #[ORM\Column(length: 500)]
    private ?string $url = null;

    #[ORM\Column(length: 32, enumType: VideoProvider::class)]
    private ?VideoProvider $provider = null;

    #[ORM\Column(length: 100)]
    private ?string $externalId = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $thumbnailUrl = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column(options: ['default' => true])]
    private bool $visibility = true;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $token = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    #[ORM\ManyToOne(targetEntity: Page::class)]
    #[ORM\JoinColumn(name: 'page_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Page $page = null;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

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

    public function setTitle(?string $title): static
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

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getProvider(): ?VideoProvider
    {
        return $this->provider;
    }

    public function setProvider(VideoProvider $provider): static
    {
        $this->provider = $provider;

        return $this;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(string $externalId): static
    {
        $this->externalId = $externalId;

        return $this;
    }

    public function getThumbnailUrl(): ?string
    {
        return $this->thumbnailUrl;
    }

    public function setThumbnailUrl(?string $thumbnailUrl): static
    {
        $this->thumbnailUrl = $thumbnailUrl;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
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

    public function isVisibility(): bool
    {
        return $this->visibility;
    }

    public function setVisibility(bool $visibility): static
    {
        $this->visibility = $visibility;

        return $this;
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

    public function resetToken(): void
    {
        $this->token = $this->generateToken();
    }

    private function generateToken(): string
    {
        try {
            return bin2hex(random_bytes(32));
        } catch (Exception $e) {
            return bin2hex(openssl_random_pseudo_bytes(32));
        }
    }

    public function getPage(): ?Page
    {
        return $this->page;
    }

    public function setPage(?Page $page): static
    {
        $this->page = $page;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
