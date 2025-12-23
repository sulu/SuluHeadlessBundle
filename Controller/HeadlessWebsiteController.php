<?php

declare(strict_types=1);

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\HeadlessBundle\Controller;

use Sulu\Bundle\HeadlessBundle\Content\StructureResolverInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\UserInterface\Controller\Website\ContentController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Headless website controller that provides JSON API responses for content.
 *
 * Extends the Sulu 3.0 ContentController to add proper JSON serialization
 * for headless/API use cases.
 *
 * @template T of DimensionContentInterface
 *
 * @extends ContentController<T>
 */
class HeadlessWebsiteController extends ContentController
{
    /**
     * @param T $object
     */
    public function indexAction(
        Request $request,
        DimensionContentInterface $object,
        string $view,
        bool $preview = false,
        bool $partial = false,
    ): Response {
        $requestFormat = $request->getRequestFormat() ?? 'html';

        if ('json' === $requestFormat) {
            $locale = $object->getLocale() ?? 'en';
            $headlessData = $this->resolveHeadlessData($object, $locale);

            $response = new JsonResponse($headlessData);
            $response->headers->set('Content-Type', 'application/json');

            if (!$preview) {
                $this->enhanceSuluCacheLifeTime($response);
            }

            return $response;
        }

        return parent::indexAction($request, $object, $view, $preview, $partial);
    }

    /**
     * @param T $object
     *
     * @return array<string, mixed>
     */
    protected function resolveSuluParameters(DimensionContentInterface $object, string $webspaceKey, bool $normalize): array
    {
        $parameters = parent::resolveSuluParameters($object, $webspaceKey, $normalize);

        $locale = $object->getLocale() ?? 'en';
        $parameters['headless'] = $this->resolveHeadlessData($object, $locale);

        return $parameters;
    }

    /**
     * Resolve dimension content to a JSON-serializable headless format.
     *
     * @param T $object
     *
     * @return array<string, mixed>
     */
    protected function resolveHeadlessData(DimensionContentInterface $object, string $locale): array
    {
        return $this->container->get('sulu_headless.structure_resolver')->resolve($object, $locale);
    }

    /**
     * @return array<string, mixed>
     */
    public static function getSubscribedServices(): array
    {
        $services = parent::getSubscribedServices();
        $services['sulu_headless.structure_resolver'] = StructureResolverInterface::class;

        return $services;
    }
}
