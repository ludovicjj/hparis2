<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\NotNull;

class FaviconType extends AbstractType
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
                    maxSize: '256k',
                    mimeTypes: ['image/png'],
                    minWidth: 32,
                    maxWidth: 512,
                    minHeight: 32,
                    maxHeight: 512,
                    mimeTypesMessage: 'Format accepté : PNG.',
                    minWidthMessage: 'Le favicon doit faire au moins {{ min_width }}px de largeur ({{ width }}px fourni).',
                    maxWidthMessage: 'Le favicon ne doit pas dépasser {{ max_width }}px de largeur ({{ width }}px fourni).',
                    minHeightMessage: 'Le favicon doit faire au moins {{ min_height }}px de hauteur ({{ height }}px fourni).',
                    maxHeightMessage: 'Le favicon ne doit pas dépasser {{ max_height }}px de hauteur ({{ height }}px fourni).',
                ),
            ],
        ]);
    }
}
