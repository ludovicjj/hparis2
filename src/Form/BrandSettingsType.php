<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class BrandSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('siteName', TextType::class, [
                'label' => 'Nom du site',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Hollywood Paris',
                    'maxlength' => 100,
                ],
                'constraints' => [
                    new NotBlank(message: 'Le nom du site est obligatoire.'),
                    new Length(max: 100, maxMessage: 'Le nom du site ne doit pas dépasser {{ limit }} caractères.'),
                ],
            ])
            ->add('gscToken', TextType::class, [
                'label' => 'Google Search Console — token de vérification',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Laisser vide si non configuré',
                    'maxlength' => 100,
                ],
                'constraints' => [
                    new Length(max: 100, maxMessage: 'Le token ne doit pas dépasser {{ limit }} caractères.'),
                ],
            ]);
    }
}
