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

namespace Sulu\Bundle\HeadlessBundle\Tests\Unit\Controller;

use JMS\Serializer\SerializerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\HeadlessBundle\Controller\NavigationApiController;
use Sulu\Bundle\WebsiteBundle\Navigation\NavigationItem;
use Sulu\Bundle\WebsiteBundle\Navigation\NavigationMapperInterface;
use Sulu\Component\Webspace\Analyzer\Attributes\RequestAttributes;
use Sulu\Component\Webspace\Webspace;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class NavigationApiControllerTest extends TestCase
{
    /**
     * @var NavigationMapperInterface|ObjectProphecy
     */
    private $navigationMapper;

    /**
     * @var SerializerInterface|ObjectProphecy
     */
    private $serializer;

    /**
     * @var NavigationApiController
     */
    private $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->navigationMapper = $this->prophesize(NavigationMapperInterface::class);
        $this->serializer = $this->prophesize(SerializerInterface::class);

        $this->controller = new NavigationApiController($this->navigationMapper->reveal(), $this->serializer->reveal());
    }

    /**
     * @return mixed[]
     */
    public function provideAttributes(): array
    {
        return [
            [1, false, false],
            [2, false, false],
            [2, true, false],
            [2, true, true],
            [2, 'false', 'false'],
            [2, 'true', 'false'],
            [2, 'true', 'true'],
        ];
    }

    /**
     * @dataProvider provideAttributes
     *
     * @param string|bool $flat
     * @param string|bool $excerpt
     */
    public function testGetAction(int $depth, $flat, $excerpt): void
    {
        $request = $this->mockRequest($depth, $flat, $excerpt);

        if (\is_string($flat)) {
            $flat = 'true' === $flat ? true : false;
        }

        if (\is_string($excerpt)) {
            $excerpt = 'true' === $excerpt ? true : false;
        }

        $item = $this->prophesize(NavigationItem::class);

        $this->navigationMapper->getRootNavigation('sulu_io', 'en', $depth, $flat, 'test', $excerpt)
            ->willReturn([$item]);

        $this->serializer->serialize([$item], 'json')->willReturn('[{"id": "123-123-123"}]');

        $response = $this->controller->getAction($request, 'test');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertArrayHasKey('content-type', $response->headers->all());
        $this->assertSame('application/json', $response->headers->get('content-type'));
        $this->assertSame('[{"id": "123-123-123"}]', $response->getContent());
    }

    /**
     * @dataProvider provideAttributes
     *
     * @param string|bool $flat
     * @param string|bool $excerpt
     */
    public function testGetByParentAction(int $depth, $flat, $excerpt): void
    {
        $request = $this->mockRequest($depth, $flat, $excerpt);

        if (\is_string($flat)) {
            $flat = 'true' === $flat ? true : false;
        }

        if (\is_string($excerpt)) {
            $excerpt = 'true' === $excerpt ? true : false;
        }

        $item = $this->prophesize(NavigationItem::class);

        $this->navigationMapper->getNavigation('123-123-123', 'sulu_io', 'en', $depth, $flat, 'test', $excerpt)
            ->willReturn([$item]);

        $this->serializer->serialize([$item], 'json')->willReturn('[{"id": "123-123-123"}]');

        $response = $this->controller->getByParentAction($request, '123-123-123', 'test');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertArrayHasKey('content-type', $response->headers->all());
        $this->assertSame('application/json', $response->headers->get('content-type'));
        $this->assertSame('[{"id": "123-123-123"}]', $response->getContent());
    }

    /**
     * @param string|bool $flat
     * @param string|bool $excerpt
     */
    private function mockRequest(
        int $depth,
        $flat,
        $excerpt,
        string $webspaceKey = 'sulu_io',
        string $locale = 'en'
    ): Request {
        $webspace = $this->prophesize(Webspace::class);
        $webspace->getKey()->willReturn($webspaceKey);

        $attributes = $this->prophesize(RequestAttributes::class);
        $attributes->getAttribute('webspace')->willReturn($webspace->reveal());

        $request = $this->prophesize(Request::class);
        $request->getLocale()->willReturn($locale);

        $parameterBag = $this->prophesize(ParameterBag::class);
        $parameterBag->get('_sulu')->willReturn($attributes->reveal());
        $request->reveal()->attributes = $parameterBag->reveal();

        $request->get('depth', 1)->willReturn($depth);
        $request->get('flat', false)->willReturn($flat);
        $request->get('excerpt', false)->willReturn($excerpt);

        return $request->reveal();
    }
}
