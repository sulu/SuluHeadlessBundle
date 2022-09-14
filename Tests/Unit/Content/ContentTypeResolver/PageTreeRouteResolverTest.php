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

namespace Sulu\Bundle\HeadlessBundle\Tests\Unit\Content\ContentTypeResolver;

use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\PageTreeRouteResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Component\Content\Compat\PropertyInterface;

class PageTreeRouteResolverTest extends TestCase
{
    /**
     * @var PageTreeRouteResolver
     */
    private $pageTreeRouteResolver;

    protected function setUp(): void
    {
        $this->pageTreeRouteResolver = new PageTreeRouteResolver();
    }

    public function testGetContentType(): void
    {
        self::assertSame('page_tree_route', $this->pageTreeRouteResolver::getContentType());
    }

    public function testResolve(): void
    {
        $data = [
            'page' => [
                'uuid' => 'abcd',
                'path' => '/foo',
            ],
            'path' => '/foo/articles/bar',
            'suffix' => '/articles/bar',
        ];

        /** @var ObjectProphecy|PropertyInterface $property */
        $property = $this->prophesize(PropertyInterface::class);

        $result = $this->pageTreeRouteResolver->resolve($data, $property->reveal(), 'en');

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame('/foo/articles/bar', $result->getContent());
        $this->assertSame($data, $result->getView());
    }

    public function testResolveDataIsString(): void
    {
        /** @var ObjectProphecy|PropertyInterface $property */
        $property = $this->prophesize(PropertyInterface::class);

        $result = $this->pageTreeRouteResolver->resolve('/foo/articles/bar', $property->reveal(), 'en');

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame('/foo/articles/bar', $result->getContent());
        $this->assertSame([], $result->getView());
    }

    public function testResolveDataIsNull(): void
    {
        /** @var ObjectProphecy|PropertyInterface $property */
        $property = $this->prophesize(PropertyInterface::class);

        $result = $this->pageTreeRouteResolver->resolve(null, $property->reveal(), 'en');

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertNull($result->getContent());
        $this->assertSame([], $result->getView());
    }
}
