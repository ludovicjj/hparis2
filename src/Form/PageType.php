<?php

namespace App\Form;

use App\Entity\Page;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('metaTitleFr', TextType::class, [
                'label' => 'Meta title (FR)',
                'attr' => [
                    'placeholder' => 'Titre affiché dans l\'onglet et les SERP',
                    'maxlength' => 70,
                ],
            ])
            ->add('metaTitleEn', TextType::class, [
                'label' => 'Meta title (EN)',
                'attr' => [
                    'placeholder' => 'Title shown in the browser tab and SERP',
                    'maxlength' => 70,
                ],
            ])
            ->add('metaDescriptionFr', TextareaType::class, [
                'label' => 'Meta description (FR)',
                'attr' => [
                    'placeholder' => 'Description courte affichée dans les résultats Google',
                    'maxlength' => 200,
                    'rows' => 3,
                ],
            ])
            ->add('metaDescriptionEn', TextareaType::class, [
                'label' => 'Meta description (EN)',
                'attr' => [
                    'placeholder' => 'Short description shown in Google search results',
                    'maxlength' => 200,
                    'rows' => 3,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Page::class,
        ]);
    }
}
