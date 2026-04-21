<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\NotNull;

class HeroType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('file', FileType::class, [
            'label' => 'Fichier',
            'mapped' => false,
            'required' => false,
            'constraints' => [
                new NotNull(message: 'Veuillez sélectionner un fichier.'),
                new Image(
                    maxSize: '3M',
                    mimeTypes: ['image/jpeg', 'image/png'],
                    maxWidth: 2400,
                    maxHeight: 1600,
                    mimeTypesMessage: 'Formats acceptés : JPG, PNG.',
                    maxWidthMessage: 'Le hero ne doit pas dépasser {{ max_width }}px de largeur ({{ width }}px fourni).',
                    maxHeightMessage: 'Le hero ne doit pas dépasser {{ max_height }}px de hauteur ({{ height }}px fourni).',
                ),
            ],
        ]);
    }
}
