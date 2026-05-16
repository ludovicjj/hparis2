<?php

namespace App\Twig;

use App\Entity\Page;
use App\Repository\PageRepository;
use RuntimeException;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class PageMetaExtension extends AbstractExtension
{
    /** @var array<string, Page> */
    private array $cache = [];

    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('page_meta_title', fn (string $slug): string => $this->title($slug)),
            new TwigFunction('page_meta_description', fn (string $slug): string => $this->description($slug)),
        ];
    }

    private function title(string $slug): string
    {
        $page = $this->resolve($slug);

        // Resolve local
        return $this->isEnglish() ? (string) $page->getMetaTitleEn() : (string) $page->getMetaTitleFr();
    }

    private function description(string $slug): string
    {
        $page = $this->resolve($slug);

        // Resolve local
        return $this->isEnglish() ? (string) $page->getMetaDescriptionEn() : (string) $page->getMetaDescriptionFr();
    }

    private function resolve(string $slug): Page
    {
        if (!array_key_exists($slug, $this->cache)) {
            $page = $this->pageRepository->findOneBySlug($slug);

            if ($page === null) {
                throw new RuntimeException(sprintf('Page "%s" not found. Run "php bin/console app:seed-pages".', $slug));
            }

            $this->cache[$slug] = $page;
        }

        return $this->cache[$slug];
    }

    private function isEnglish(): bool
    {
        return $this->requestStack->getCurrentRequest()?->getLocale() === 'en';
    }
}
