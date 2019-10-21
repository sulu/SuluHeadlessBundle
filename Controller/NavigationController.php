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

use JMS\Serializer\SerializerInterface;
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

    public function __construct(NavigationMapperInterface $navigationMapper, SerializerInterface $serializer)
    {
        $this->navigationMapper = $navigationMapper;
        $this->serializer = $serializer;
    }

    public function getAction(Request $request, string $context): Response
    {
        /** @var RequestAttributes $attributes */
        $attributes = $request->attributes->get('_sulu');

        /** @var Webspace $webspace */
        $webspace = $attributes->getAttribute('webspace');
        $locale = $request->getLocale();

        $uuid = $request->query->get('uuid');
        $depth = (int) $this->getRequestParameter($request, 'depth', false, 1);
        $flat = $this->getBooleanRequestParameter($request, 'flat', false, false);

        $excerpt = $this->getBooleanRequestParameter($request, 'excerpt', false, false);

        $navigation = $this->loadNavigation($webspace->getKey(), $locale, $depth, $flat, $context, $excerpt, $uuid);

        return new Response(
            $this->serializer->serialize(
                new CollectionRepresentation($navigation, 'items'),
                'json'
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
}
