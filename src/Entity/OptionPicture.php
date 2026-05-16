<?php

namespace App\Entity;

use App\Repository\OptionPictureRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OptionPictureRepository::class)]
#[ORM\HasLifecycleCallbacks]
class OptionPicture extends AbstractEntryPicture
{
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Option $option = null;

    public function getOption(): ?Option
    {
        return $this->option;
    }

    public function setOption(?Option $option): static
    {
        $this->option = $option;

        return $this;
    }
}
