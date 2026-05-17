<?php

namespace App\Form;

use App\Entity\Team;
use App\Service\Video\VideoThumbnailResolver;
use App\Service\Video\VideoUrlParser;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class TeamType extends AbstractType
{
    public function __construct(
        private readonly VideoUrlParser $videoUrlParser,
        private readonly VideoThumbnailResolver $videoThumbnailResolver,
        #[Autowire(service: 'html_sanitizer.sanitizer.app.video_description')]
        private readonly HtmlSanitizerInterface $descriptionSanitizer,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Titre (facultatif)',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Description (facultatif)',
                    'rows' => 6,
                    'data-ckeditor' => 'true',
                ],
            ])
            ->add('url', UrlType::class, [
                'label' => 'Lien de la vidéo',
                'required' => false,
                'attr' => [
                    'placeholder' => 'https://www.youtube.com/watch?v=...',
                ],
                'constraints' => [
                    new Callback([$this, 'validateProviderUrl']),
                ],
            ])
            ->add('visibility', CheckboxType::class, [
                'label' => 'Visibilité',
                'required' => false,
            ]);

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event): void {
            /** @var Team|null $team */
            $team = $event->getData();

            if ($team === null) {
                return;
            }

            if ($team->getUrl() !== null) {
                $parsed = $this->videoUrlParser->parse($team->getUrl());

                if ($parsed !== null) {
                    $team->setProvider($parsed['provider']);
                    $team->setExternalId($parsed['externalId']);
                    $team->setThumbnailUrl(
                        $this->videoThumbnailResolver->resolve($parsed['provider'], $parsed['externalId'])
                    );
                }
            } else {
                $team
                    ->setProvider(null)
                    ->setExternalId(null)
                    ->setThumbnailUrl(null);
            }

            if ($team->getDescription() !== null) {
                $sanitized = $this->descriptionSanitizer->sanitize($team->getDescription());

                $sanitized = preg_replace('~<p>[\s\x{00A0}]*</p>~u', '', $sanitized);
                $sanitized = trim($sanitized);
                $team->setDescription($sanitized === '' ? null : $sanitized);
            }
        });
    }

    public function validateProviderUrl(mixed $url, ExecutionContextInterface $context): void
    {
        if (!is_string($url) || $url === '') {
            return;
        }

        if ($this->videoUrlParser->parse($url) === null) {
            $context->buildViolation('URL non reconnue. Utilisez un lien YouTube, Vimeo ou Dailymotion.')
                ->addViolation();
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Team::class,
        ]);
    }
}
