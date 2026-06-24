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
use Sulu\Component\Localization\Localization;
use Sulu\Component\Webspace\Analyzer\Attributes\RequestAttributes;
use Sulu\Component\Webspace\Webspace;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Domain\Model\RoutableInterface;
use Sulu\Content\Infrastructure\Sulu\Route\Exception\WebspaceUrlNotFoundException;
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
            $headlessData['localizations'] = $this->resolveHeadlessLocalizations(
                $object,
                $this->getHeadlessWebspaceKey($request),
            );

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
        $parameters['headless']['localizations'] = $parameters['localizations'] ?? [];

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

    // TODO: remove once getSuluWebspaceKey() and resolveSuluLocalizations() are moved out of ContentController
    //       see https://github.com/sulu/sulu/issues/8175
    private function getHeadlessWebspaceKey(Request $request): string
    {
        $suluAttribute = $request->attributes->get('_sulu');

        \assert($suluAttribute instanceof RequestAttributes, 'The "_sulu" request attribute must be of type ' . RequestAttributes::class . ', but got: ' . \get_debug_type($suluAttribute));
        $attributes = $suluAttribute->getAttributes();
        $webspace = $attributes['webspace'];
        \assert($webspace instanceof Webspace, 'The "webspace" request attribute must be of type ' . Webspace::class . ', but got: ' . \get_debug_type($webspace));

        return $webspace->getKey();
    }

    /**
     * @param T $object
     *
     * @return array<string, array{url: string, locale: string, alternate: bool}>
     *
     * TODO: remove once getSuluWebspaceKey() and resolveSuluLocalizations() are moved out of ContentController
     *       see https://github.com/sulu/sulu/issues/8175
     */
    private function resolveHeadlessLocalizations(DimensionContentInterface $object, string $webspaceKey): array
    {
        if (!$object instanceof RoutableInterface) {
            return [];
        }

        $webspaceLocales = $this->container->get('sulu_core.webspace.webspace_manager')
            ->getWebspaceCollection()
            ->getWebspace($webspaceKey)
            ?->getAllLocalizations() ?? [];

        $localizations = [];
        foreach ($webspaceLocales as $webspaceLocale) {
            $locale = $webspaceLocale->getLocale(Localization::UNDERSCORE);
            try {
                $localizations[$locale] = [
                    'url' => $this->container->get('sulu_route.route_generator')->generate(
                        '/',
                        $locale,
                        $webspaceKey,
                    ),
                    'locale' => $locale,
                    'alternate' => false,
                ];
            } catch (WebspaceUrlNotFoundException) {
                continue;
            }
        }

        $routes = [];
        foreach ($this->container->get('sulu_route.route_repository')->findBy([
            'resourceKey' => $object::getResourceKey(),
            'resourceId' => (string) $object->getResource()->getId(),
            'locales' => $object->getAvailableLocales() ?? [],
        ]) as $route) {
            $routes[] = $route;
        }

        foreach ($routes as $route) {
            $locale = $route->getLocale();
            try {
                $localizations[$locale] = [
                    'url' => $this->container->get('sulu_route.route_generator')->generate(
                        $route->getSlug(),
                        $route->getLocale(),
                        $webspaceKey,
                    ),
                    'locale' => $locale,
                    'alternate' => true,
                ];
            } catch (WebspaceUrlNotFoundException) {
                continue;
            }
        }

        return $localizations;
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
