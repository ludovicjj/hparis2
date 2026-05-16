<?php

namespace App\Service\Option;

use App\Entity\Option;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

readonly class OptionService
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Generate the public URL for an option.
     * If the option is private (visibility = false), the token is included as a query parameter.
     */
    public function generatePublicUrl(Option $option): string
    {
        $params = ['id' => $option->getId()];

        if (!$option->isVisibility()) {
            $params['token'] = $option->getToken();
        }

        return $this->urlGenerator->generate(
            'app_front_options_show',
            $params,
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    public function canAccessOption(Option $option, ?string $token): bool
    {
        if ($option->isVisibility()) {
            return true;
        }

        $optionToken = $option->getToken();
        if ($optionToken === null || $token === null) {
            return false;
        }

        return hash_equals($optionToken, $token);
    }
}
