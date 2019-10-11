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
use Sulu\Component\Rest\RequestParametersTrait;
use Sulu\Component\Webspace\Analyzer\Attributes\RequestAttributes;
use Sulu\Component\Webspace\Webspace;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class NavigationApiController
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

        $navigation = $this->navigationMapper->getRootNavigation(
            $webspace->getKey(),
            $locale,
            (int) $this->getRequestParameter($request, 'depth', false, 1),
            $this->getBooleanRequestParameter($request, 'flat', false, false),
            $context,
            $this->getBooleanRequestParameter($request, 'excerpt', false, false)
        );

        return new Response(
            $this->serializer->serialize($navigation, 'json'),
            200,
            [
                'Content-Type' => 'application/json',
            ]
        );
    }

    public function getByParentAction(Request $request, string $uuid, string $context): Response
    {
        /** @var RequestAttributes $attributes */
        $attributes = $request->attributes->get('_sulu');

        /** @var Webspace $webspace */
        $webspace = $attributes->getAttribute('webspace');
        $locale = $request->getLocale();

        $navigation = $this->navigationMapper->getNavigation(
            $uuid,
            $webspace->getKey(),
            $locale,
            (int) $this->getRequestParameter($request, 'depth', false, 1),
            $this->getBooleanRequestParameter($request, 'flat', false, false),
            $context,
            $this->getBooleanRequestParameter($request, 'excerpt', false, false)
        );

        return new Response(
            $this->serializer->serialize($navigation, 'json'),
            200,
            [
                'Content-Type' => 'application/json',
            ]
        );
    }
}
