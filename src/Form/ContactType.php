<?php

namespace App\Form;

use Karser\Recaptcha3Bundle\Form\Recaptcha3Type;
use Karser\Recaptcha3Bundle\Validator\Constraints\Recaptcha3;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => [
                    'placeholder' => 'votre.email@exemple.com',
                ],
                'constraints' => [
                    new NotBlank(message: 'Veuillez renseigner votre email.'),
                    new Email(message: 'L\'email n\'est pas valide.'),
                ],
            ])
            ->add('subject', TextType::class, [
                'label' => 'Sujet',
                'attr' => [
                    'placeholder' => 'Sujet de votre message',
                ],
                'constraints' => [
                    new NotBlank(message: 'Veuillez renseigner un sujet.'),
                    new Length(max: 150, maxMessage: 'Le sujet ne peut pas dépasser {{ limit }} caractères.'),
                ],
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Message',
                'attr' => [
                    'placeholder' => 'Votre message...',
                    'rows' => 8,
                ],
                'constraints' => [
                    new NotBlank(message: 'Veuillez renseigner un message.'),
                    new Length(min: 10, max: 5000, minMessage: 'Le message doit faire au moins {{ limit }} caractères.', maxMessage: 'Le message ne peut pas dépasser {{ limit }} caractères.'),
                ],
            ])
            ->add('captcha', Recaptcha3Type::class, [
                'constraints' => [new Recaptcha3()],
                'action_name' => 'contact',
                'locale' => 'fr',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
