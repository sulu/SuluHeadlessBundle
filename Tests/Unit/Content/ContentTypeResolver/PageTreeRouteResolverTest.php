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
use Prophecy\PhpUnit\ProphecyTrait;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\PageTreeRouteResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;

class PageTreeRouteResolverTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var PageTreeRouteResolver
     */
    private $pageTreeRouteResolver;

    private FieldMetadata $fieldMetadata;

    protected function setUp(): void
    {
        $this->pageTreeRouteResolver = new PageTreeRouteResolver();
        $this->fieldMetadata = new FieldMetadata('page_tree_route');
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

        $result = $this->pageTreeRouteResolver->resolve($data, $this->fieldMetadata, 'en');

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame('/foo/articles/bar', $result->getContent());
        $this->assertSame($data, $result->getView());
    }

    public function testResolveDataIsString(): void
    {
        $result = $this->pageTreeRouteResolver->resolve('/foo/articles/bar', $this->fieldMetadata, 'en');

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame('/foo/articles/bar', $result->getContent());
        $this->assertSame([], $result->getView());
    }

    public function testResolveDataIsNull(): void
    {
        $result = $this->pageTreeRouteResolver->resolve(null, $this->fieldMetadata, 'en');

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertNull($result->getContent());
        $this->assertSame([], $result->getView());
    }
}
