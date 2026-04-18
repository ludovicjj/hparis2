<?php

namespace App\Form;

use App\Entity\Category;
use App\Form\DataTransformer\CategoryCollectionToIdsTransformer;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SearchCategoryType extends AbstractType
{
    public function __construct(
        private readonly CategoryCollectionToIdsTransformer $transformer,
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('search');
        $resolver->setDefaults([
            'compound' => false,
            'multiple' => true,
            'required' => false,
            'placeholder' => 'Rechercher une catégorie',
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer($this->transformer);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['expanded'] = false;
        $view->vars['multiple'] = $options['multiple'];
        $view->vars['placeholder'] = $options['placeholder'];
        $view->vars['placeholder_in_choices'] = false;
        $view->vars['preferred_choices'] = [];
        $view->vars['choices'] = $this->buildChoices($form->getData());
        $view->vars['choice_translation_domain'] = false;
        $view->vars['required'] = $options['required'];
        $view->vars['attr']['data-remote'] = $options['search'];

        if ($options['multiple']) {
            $view->vars['full_name'] .= '[]';
        }
    }

    public function getBlockPrefix(): string
    {
        return 'choice';
    }

    /**
     * @param Collection<int, Category>|null $collection
     * @return array<int, ChoiceView>
     */
    private function buildChoices(?Collection $collection): array
    {
        if ($collection === null || $collection->isEmpty()) {
            return [];
        }

        return $collection
            ->map(fn (Category $c) => new ChoiceView($c, (string) $c->getId(), $c->getName()))
            ->toArray();
    }
}
