<?php

namespace App\Form;

use App\Entity\SocialLink;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SocialLinkType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('platform', ChoiceType::class, [
                'label' => 'Plateforme',
                'choices' => [
                    'Instagram' => 'instagram',
                    'Facebook' => 'facebook',
                    'TikTok' => 'tiktok',
                    'YouTube' => 'youtube',
                    'LinkedIn' => 'linkedin',
                    'Twitter / X' => 'twitter',
                    'Pinterest' => 'pinterest',
                    'Snapchat' => 'snapchat',
                ],
                'placeholder' => 'Choisir une plateforme',
            ])
            ->add('url', UrlType::class, [
                'label' => 'URL',
                'attr' => [
                    'placeholder' => 'https://...',
                ],
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'Actif',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SocialLink::class,
        ]);
    }
}
