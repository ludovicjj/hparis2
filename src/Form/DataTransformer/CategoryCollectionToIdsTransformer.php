<?php

namespace App\Form\DataTransformer;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Form\DataTransformerInterface;

readonly class CategoryCollectionToIdsTransformer implements DataTransformerInterface
{
    public function __construct(
        private CategoryRepository $categoryRepository,
    ) {
    }

    /**
     * Model (Collection<Category>) → Norm (array of string IDs for the <select>)
     */
    public function transform(mixed $value): array
    {
        if (!$value instanceof Collection || $value->isEmpty()) {
            return [];
        }

        return $value
            ->map(fn (Category $c) => (string) $c->getId())
            ->toArray();
    }

    /**
     * Norm (array of string IDs from the POSTed <select>) → Model (Collection<Category>)
     */
    public function reverseTransform(mixed $value): Collection
    {
        if (empty($value)) {
            return new ArrayCollection();
        }

        return new ArrayCollection(
            $this->categoryRepository->findBy(['id' => $value])
        );
    }
}
