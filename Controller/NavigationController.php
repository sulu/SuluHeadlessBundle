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

use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\MediaSerializerInterface;
use Sulu\Bundle\HttpCacheBundle\Cache\SuluHttpCache;
use Sulu\Bundle\WebsiteBundle\Navigation\NavigationMapperInterface;
use Sulu\Bundle\WebsiteBundle\ReferenceStore\ReferenceStoreInterface;
use Sulu\Component\Rest\ListBuilder\CollectionRepresentation;
use Sulu\Component\Rest\RequestParametersTrait;
use Sulu\Component\Webspace\Analyzer\Attributes\RequestAttributes;
use Sulu\Component\Webspace\Webspace;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class NavigationController
{
    use RequestParametersTrait;

    /**
     * @var NavigationMapperInterface
     */
    private $navigationMapper;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var MediaSerializerInterface
     */
    private $mediaSerializer;

    /**
     * @var ReferenceStoreInterface
     */
    private $navigationReferenceStore;

    /**
     * @var int
     */
    private $maxAge;

    /**
     * @var int
     */
    private $sharedMaxAge;

    /**
     * @var int
     */
    private $cacheLifetime;

    public function __construct(
        NavigationMapperInterface $navigationMapper,
        SerializerInterface $serializer,
        MediaSerializerInterface $mediaSerializer,
        ReferenceStoreInterface $pageReferenceStore,
        int $maxAge,
        int $sharedMaxAge,
        int $cacheLifetime
    ) {
        $this->navigationMapper = $navigationMapper;
        $this->serializer = $serializer;
        $this->mediaSerializer = $mediaSerializer;
        $this->navigationReferenceStore = $pageReferenceStore;
        $this->maxAge = $maxAge;
        $this->sharedMaxAge = $sharedMaxAge;
        $this->cacheLifetime = $cacheLifetime;
    }

    public function getAction(Request $request, string $context): Response
    {
        /** @var RequestAttributes $attributes */
        $attributes = $request->attributes->get('_sulu');

        /** @var Webspace $webspace */
        $webspace = $attributes->getAttribute('webspace');
        $locale = $request->getLocale();

        /** @var string $uuid */
        $uuid = $request->query->get('uuid');
        $depth = (int) $this->getRequestParameter($request, 'depth', false, 1);
        $flat = $this->getBooleanRequestParameter($request, 'flat', false, false);
        $excerpt = $this->getBooleanRequestParameter($request, 'excerpt', false, false);

        $navigation = $this->loadNavigation($webspace->getKey(), $locale, $depth, $flat, $context, $excerpt, $uuid);

        $this->navigationReferenceStore->add($context);

        // need to serialize the media entities inside the excerpt to keep the media serialization consistent
        $navigation = $this->serializeExcerptMedia($navigation, $locale);

        $response = new Response(
            $this->serializer->serialize(
                new CollectionRepresentation($navigation, 'items'),
                'json',
                (new SerializationContext())->setSerializeNull(true)
            ),
            200,
            [
                'Content-Type' => 'application/json',
            ]
        );

        $response->setPublic();
        $response->setMaxAge($this->maxAge);
        $response->setSharedMaxAge($this->sharedMaxAge);
        $response->headers->set(SuluHttpCache::HEADER_REVERSE_PROXY_TTL, (string) $this->cacheLifetime);

        return $response;
    }

    /**
     * @return mixed[]
     */
    protected function loadNavigation(
        string $webspaceKey,
        string $locale,
        int $depth,
        bool $flat,
        string $context,
        bool $excerpt,
        ?string $uuid = null
    ): array {
        if ($uuid) {
            return $this->navigationMapper->getNavigation(
                $uuid,
                $webspaceKey,
                $locale,
                $depth,
                $flat,
                $context,
                $excerpt
            );
        }

        return $this->navigationMapper->getRootNavigation(
            $webspaceKey,
            $locale,
            $depth,
            $flat,
            $context,
            $excerpt
        );
    }

    /**
     * @param mixed[] $navigation
     *
     * @return mixed[]
     */
    private function serializeExcerptMedia(array $navigation, string $locale): array
    {
        foreach ($navigation as $itemIndex => $navigationItem) {
            if (isset($navigationItem['excerpt'])) {
                foreach ($navigationItem['excerpt']['icon'] as $iconIndex => $iconMedia) {
                    $navigation[$itemIndex]['excerpt']['icon'][$iconIndex] = $this->mediaSerializer->serialize(
                        $iconMedia->getEntity(),
                        $locale
                    );
                }

                foreach ($navigationItem['excerpt']['images'] as $imageIndex => $imageMedia) {
                    $navigation[$itemIndex]['excerpt']['images'][$imageIndex] = $this->mediaSerializer->serialize(
                        $imageMedia->getEntity(),
                        $locale
                    );
                }
            }

            // recursively serialize all excerpt medias
            if (!empty($navigationItem['children'])) {
                $navigation[$itemIndex]['children'] = $this->serializeExcerptMedia($navigationItem['children'], $locale);
            }
        }

        return $navigation;
    }
}
