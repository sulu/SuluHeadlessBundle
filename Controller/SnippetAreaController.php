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
use Sulu\Bundle\HttpCacheBundle\Cache\SuluHttpCache;
use Sulu\Bundle\HttpCacheBundle\CacheLifetime\CacheLifetimeRequestStore;
use Sulu\Bundle\HttpCacheBundle\ReferenceStore\ReferenceStoreInterface;
use Sulu\Component\Rest\RequestParametersTrait;
use Sulu\Component\Webspace\Analyzer\Attributes\RequestAttributes;
use Sulu\Component\Webspace\Webspace;
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Infrastructure\Doctrine\DimensionContentQueryEnhancer;
use Sulu\Snippet\Domain\Model\SnippetDimensionContentInterface;
use Sulu\Snippet\Domain\Repository\SnippetAreaRepositoryInterface;
use Sulu\Snippet\Domain\Repository\SnippetRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SnippetAreaController
{
    use RequestParametersTrait;

    public function __construct(
        private SnippetAreaRepositoryInterface $snippetAreaRepository,
        private SnippetRepositoryInterface $snippetRepository,
        private ContentAggregatorInterface $contentAggregator,
        private StructureResolverInterface $structureResolver,
        private ?ReferenceStoreInterface $snippetAreaReferenceStore,
        private int $maxAge,
        private int $sharedMaxAge,
        private int $cacheLifetime,
        private ?CacheLifetimeRequestStore $cacheLifetimeRequestStore = null,
    ) {
    }

    public function getAction(Request $request, string $area): Response
    {
        /** @var RequestAttributes $attributes */
        $attributes = $request->attributes->get('_sulu');

        /** @var Webspace $webspace */
        $webspace = $attributes->getAttribute('webspace');
        $webspaceKey = $webspace->getKey();
        $locale = $request->getLocale();

        $includeExtension = $this->getBooleanRequestParameter($request, 'includeExtension', false, false);

        $snippetArea = $this->snippetAreaRepository->findOneBy([
            'webspaceKey' => $webspaceKey,
            'areaKey' => $area,
        ]);

        if (null === $snippetArea || null === $snippetArea->getSnippet()) {
            throw new NotFoundHttpException(\sprintf('No snippet found for snippet area "%s"', $area));
        }

        $snippet = $this->snippetRepository->findOneBy(
            [
                'uuid' => $snippetArea->getSnippet()->getUuid(),
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_LIVE,
                'version' => DimensionContentInterface::CURRENT_VERSION,
            ],
            [
                SnippetRepositoryInterface::SELECT_SNIPPET_CONTENT => [
                    DimensionContentQueryEnhancer::GROUP_SELECT_CONTENT_WEBSITE => true,
                ],
            ]
        );

        if (null === $snippet) {
            throw new NotFoundHttpException(\sprintf('Snippet for snippet area "%s" does not exist in locale "%s"', $area, $locale));
        }

        /** @var SnippetDimensionContentInterface $dimensionContent */
        $dimensionContent = $this->contentAggregator->aggregate(
            $snippet,
            [
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_LIVE,
                'version' => DimensionContentInterface::CURRENT_VERSION,
            ]
        );

        if ($this->snippetAreaReferenceStore) {
            $this->snippetAreaReferenceStore->add($area, 'snippet_area');
        }

        $resolvedSnippet = $this->structureResolver->resolve(
            $dimensionContent,
            $locale,
            $includeExtension
        );

        $response = new JsonResponse($resolvedSnippet);

        $response->setPublic();
        $response->setMaxAge($this->maxAge);
        $response->setSharedMaxAge($this->sharedMaxAge);

        $cacheLifetime = $this->cacheLifetime;
        if (null !== $this->cacheLifetimeRequestStore) {
            $this->cacheLifetimeRequestStore->setCacheLifetime($this->cacheLifetime);
            $cacheLifetime = $this->cacheLifetimeRequestStore->getCacheLifetime();
        }

        $response->headers->set(
            SuluHttpCache::HEADER_REVERSE_PROXY_TTL,
            (string) $cacheLifetime
        );

        return $response;
    }
}
