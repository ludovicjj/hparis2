<?php

namespace App\Form;

use Karser\Recaptcha3Bundle\Form\Recaptcha3Type;
use Karser\Recaptcha3Bundle\Validator\Constraints\Recaptcha3;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ContactType extends AbstractType
{
    public function __construct(private readonly RequestStack $requestStack) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $locale = $this->requestStack->getCurrentRequest()?->getLocale() ?? 'fr';


        $builder
            ->add('email', EmailType::class, [
                'label' => 'contact.form.email_label',
                'constraints' => [
                    new NotBlank(message: 'contact.email.required'),
                    new Email(message: 'contact.email.invalid'),
                ],
            ])
            ->add('subject', TextType::class, [
                'label' => 'contact.form.subject_label',
                'constraints' => [
                    new NotBlank(message: 'contact.subject.required'),
                    new Length(max: 150, maxMessage: 'contact.subject.too_long'),
                ],
            ])
            ->add('message', TextareaType::class, [
                'label' => 'contact.form.message_label',
                'attr' => [
                    'rows' => 8,
                ],
                'constraints' => [
                    new NotBlank(message: 'contact.message.required'),
                    new Length(min: 10, max: 5000, minMessage: 'contact.message.too_short', maxMessage: 'contact.message.too_long'),
                ],
            ])
            ->add('captcha', Recaptcha3Type::class, [
                'constraints' => [new Recaptcha3()],
                'action_name' => 'contact',
                'locale' => $locale,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
