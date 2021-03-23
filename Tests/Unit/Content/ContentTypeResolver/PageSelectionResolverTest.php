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
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\PageSelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\StructureResolverInterface;
use Sulu\Component\Content\Compat\PropertyInterface;
use Sulu\Component\Content\Compat\PropertyParameter;
use Sulu\Component\Content\Compat\StructureInterface;
use Sulu\Component\Content\Mapper\ContentMapperInterface;
use Sulu\Component\Content\Query\ContentQueryBuilderInterface;

class PageSelectionResolverTest extends TestCase
{
    /**
     * @var StructureResolverInterface|ObjectProphecy
     */
    private $structureResolver;

    /**
     * @var ContentQueryBuilderInterface|ObjectProphecy
     */
    private $contentQueryBuilder;

    /**
     * @var ContentMapperInterface|ObjectProphecy
     */
    private $contentMapper;

    /**
     * @var PageSelectionResolver
     */
    private $pageSelectionResolver;

    protected function setUp(): void
    {
        $this->structureResolver = $this->prophesize(StructureResolverInterface::class);
        $this->contentQueryBuilder = $this->prophesize(ContentQueryBuilderInterface::class);
        $this->contentMapper = $this->prophesize(ContentMapperInterface::class);

        $this->pageSelectionResolver = new PageSelectionResolver(
            $this->structureResolver->reveal(),
            $this->contentQueryBuilder->reveal(),
            $this->contentMapper->reveal(),
            true
        );
    }

    public function testGetContentType(): void
    {
        self::assertSame('page_selection', $this->pageSelectionResolver::getContentType());
    }

    public function testResolve(): void
    {
        $structure = $this->prophesize(StructureInterface::class);
        $structure->getWebspaceKey()->willReturn('webspace-key');

        /** @var PropertyInterface|ObjectProphecy $property */
        $property = $this->prophesize(PropertyInterface::class);
        $params = [
            'properties' => new PropertyParameter('properties', [
                new PropertyParameter('contentDescription', 'description'),
                new PropertyParameter('excerptTitle', 'excerpt.title'),
                new PropertyParameter('categories', 'excerpt.categories'),
            ]),
        ];

        $property->getParams()->willReturn($params);
        $property->getStructure()->willReturn($structure->reveal());

        // expected and unexpected service calls
        $this->contentQueryBuilder->init([
            'ids' => ['page-id-1', 'page-id-2'],
            'properties' => $params['properties']->getValue(),
            'published' => false,
        ])->shouldBeCalled();
        $this->contentQueryBuilder->build('webspace-key', ['en'])->willReturn(['page-query-string']);

        $pageStructure1 = $this->prophesize(StructureInterface::class);
        $pageStructure2 = $this->prophesize(StructureInterface::class);
        $this->contentMapper->loadBySql2(
            'page-query-string',
            'en',
            'webspace-key'
        )->willReturn([
            $pageStructure1->reveal(),
            $pageStructure2->reveal(),
        ])->shouldBeCalledOnce();
        $this->structureResolver->resolveProperties(
            $pageStructure1->reveal(),
            [
                'title' => 'title',
                'url' => 'url',
                'contentDescription' => 'description',
                'excerptTitle' => 'excerpt.title',
                'categories' => 'excerpt.categories',
            ],
            'en'
        )->willReturn([
            'id' => 'page-id-1',
            'template' => 'default',
            'content' => [
                'title' => 'Page Title 1',
                'url' => '/page-url-1',
                'contentDescription' => 'Page Content Description',
                'excerptTitle' => 'Page Excerpt Title 1',
                'categories' => [],
            ],
            'view' => [
                'title' => [],
                'url' => [],
                'contentDescription' => [],
                'excerptTitle' => [],
                'categories' => [],
            ],
        ])->shouldBeCalledOnce();

        $this->structureResolver->resolveProperties(
            $pageStructure2->reveal(),
            [
                'title' => 'title',
                'url' => 'url',
                'contentDescription' => 'description',
                'excerptTitle' => 'excerpt.title',
                'categories' => 'excerpt.categories',
            ],
            'en'
        )->willReturn([
            'id' => 'page-id-2',
            'template' => 'default',
            'content' => [
                'title' => 'Page Title 2',
                'url' => '/page-url-2',
                'contentDescription' => 'Page Content Description',
                'excerptTitle' => 'Page Excerpt Title 2',
                'categories' => [],
            ],
            'view' => [
                'title' => [],
                'url' => [],
                'contentDescription' => [],
                'excerptTitle' => [],
                'categories' => [],
            ],
        ])->shouldBeCalledOnce();

        // call test function
        $result = $this->pageSelectionResolver->resolve(
            ['page-id-1', 'page-id-2'],
            $property->reveal(),
            'en'
        );

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame(
            [
                [
                    'id' => 'page-id-1',
                    'template' => 'default',
                    'content' => [
                        'title' => 'Page Title 1',
                        'url' => '/page-url-1',
                        'contentDescription' => 'Page Content Description',
                        'excerptTitle' => 'Page Excerpt Title 1',
                        'categories' => [],
                    ],
                    'view' => [
                        'title' => [],
                        'url' => [],
                        'contentDescription' => [],
                        'excerptTitle' => [],
                        'categories' => [],
                    ],
                ],
                [
                    'id' => 'page-id-2',
                    'template' => 'default',
                    'content' => [
                        'title' => 'Page Title 2',
                        'url' => '/page-url-2',
                        'contentDescription' => 'Page Content Description',
                        'excerptTitle' => 'Page Excerpt Title 2',
                        'categories' => [],
                    ],
                    'view' => [
                        'title' => [],
                        'url' => [],
                        'contentDescription' => [],
                        'excerptTitle' => [],
                        'categories' => [],
                    ],
                ],
            ],
            $result->getContent()
        );

        $this->assertSame(
            ['ids' => ['page-id-1', 'page-id-2']],
            $result->getView()
        );
    }

    public function testResolveDataIsNull(): void
    {
        $locale = 'en';
        $property = $this->prophesize(PropertyInterface::class);

        // expected and unexpected service calls
        $this->contentQueryBuilder->init(Argument::cetera())
            ->shouldNotBeCalled();

        $this->structureResolver->resolve(Argument::cetera())
            ->shouldNotBeCalled();

        // call test function
        $result = $this->pageSelectionResolver->resolve(null, $property->reveal(), $locale);

        $this->assertSame([], $result->getContent());

        $this->assertSame(['ids' => []], $result->getView());
    }

    public function testResolveDataIsEmptyArray(): void
    {
        $locale = 'en';
        $property = $this->prophesize(PropertyInterface::class);

        // expected and unexpected service calls
        $this->contentQueryBuilder->init(Argument::any())
            ->shouldNotBeCalled();

        $this->structureResolver->resolve(Argument::any())
            ->shouldNotBeCalled();

        // call test function
        $result = $this->pageSelectionResolver->resolve([], $property->reveal(), $locale);

        $this->assertSame([], $result->getContent());

        $this->assertSame(['ids' => []], $result->getView());
    }
}
