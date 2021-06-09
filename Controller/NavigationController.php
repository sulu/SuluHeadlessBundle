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
use Sulu\Bundle\WebsiteBundle\Navigation\NavigationMapperInterface;
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

    public function __construct(
        NavigationMapperInterface $navigationMapper,
        SerializerInterface $serializer,
        MediaSerializerInterface $mediaSerializer
    ) {
        $this->navigationMapper = $navigationMapper;
        $this->serializer = $serializer;
        $this->mediaSerializer = $mediaSerializer;
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

        // need to serialize the media entities inside the excerpt to keep the media serialization consistent
        $navigation = $this->serializeExcerptMedia($navigation, $locale);

        return new Response(
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

        return $navigation = $this->navigationMapper->getRootNavigation(
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
            if (\array_key_exists('excerpt', $navigationItem)) {
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
        }

        return $navigation;
    }
}
