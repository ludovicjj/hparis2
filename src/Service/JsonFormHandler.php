<?php

namespace App\Service;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class JsonFormHandler
{
    /**
     * Handle the request on the form and return a 422 JsonResponse with
     * serialized errors if the form is not valid. Returns null when the form
     * is submitted and valid — caller proceeds with business logic.
     */
    public function getValidationErrorResponse(FormInterface $form, Request $request): ?JsonResponse
    {
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            return null;
        }

        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $origin = $error->getOrigin();
            $field = $origin ? $origin->getName() : '_global';
            $errors[$field] = $error->getMessage();
        }

        return new JsonResponse(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
