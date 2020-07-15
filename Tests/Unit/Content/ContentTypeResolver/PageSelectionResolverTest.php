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
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\PageSelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\StructureResolverInterface;
use Sulu\Bundle\PageBundle\Content\PageSelectionContainer;
use Sulu\Component\Content\Compat\PropertyInterface;
use Sulu\Component\Content\Compat\PropertyParameter;
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
        $locale = 'en';
        $data = [1];

        /** @var PropertyInterface|ObjectProphecy $property */
        $property = $this->prophesize(PropertyInterface::class);
        $params = [
            'properties' => new PropertyParameter('properties', [
                new PropertyParameter('title', 'title'),
                new PropertyParameter('excerptTitle', 'excerpt.title'),
                new PropertyParameter('categories', 'excerpt.categories'),
            ]),
        ];
        $property->getParams()->willReturn($params);

        $container = $this->prophesize(PageSelectionContainer::class);
        $this->contentQueryBuilder->createContainer($data, $params, 'sulu', $locale)
            ->willReturn($container->reveal());

        $pages = [
            [
                'id' => '2',
                'uuid' => '1',
                'nodeType' => 1,
                'path' => '/testpage',
                'changer' => 1,
                'publishedState' => true,
                'creator' => 1,
                'title' => 'TestPage',
                'locale' => 'en',
                'webspaceKey' => 'sulu',
                'template' => 'headless',
                'parent' => '1',
                'author' => '2',
                'order' => 30,
                'description' => 'Main Test Description',
                'excerptDescription' => 'Excerpt Test Description',
                'excerptCategories' => [
                    [
                        'id' => 2,
                        'key' => 'testcat_1',
                        'name' => 'TestCat_1_en',
                        'defaultLocale' => 'en',
                        'creator' => 'Adam Ministrator',
                        'changer' => 'Adam Ministrator',
                    ],
                ],
            ],
        ];

        $container->getData()->willReturn($pages);
        $this->structureResolver->serialize($pages[0], $params['properties']->getValue())->willReturn(
            [
                'id' => '2',
                'uuid' => '1',
                'nodeType' => 1,
                'path' => '/testpage',
                'changer' => 1,
                'publishedState' => true,
                'creator' => 1,
                'title' => 'TestPage',
                'locale' => 'en',
                'webspaceKey' => 'sulu',
                'template' => 'headless',
                'parent' => '1',
                'author' => '2',
                'order' => 30,
                'description' => 'Main Test Description',
                'excerptDescription' => 'Excerpt Test Description',
                'excerptCategories' => [
                    [
                        'id' => 2,
                        'key' => 'testcat_1',
                        'name' => 'TestCat_1_en',
                        'creator' => 'Adam Ministrator',
                        'changer' => 'Adam Ministrator',
                        'medias' => [
                            [
                                'id' => 1,
                                'formatUri' => '/media/1/{format}/media-1.jpg?v=1-0',
                            ],
                        ],
                    ],
                ],
            ]
        );

        $result = $this->pageSelectionResolver->resolve($data, $property->reveal(), $locale, ['webspaceKey' => 'sulu']);

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame(
            [
                [
                    'id' => '2',
                    'uuid' => '1',
                    'nodeType' => 1,
                    'path' => '/testpage',
                    'changer' => 1,
                    'publishedState' => true,
                    'creator' => 1,
                    'title' => 'TestPage',
                    'locale' => 'en',
                    'webspaceKey' => 'sulu',
                    'template' => 'headless',
                    'parent' => '1',
                    'author' => '2',
                    'order' => 30,
                    'description' => 'Main Test Description',
                    'excerptDescription' => 'Excerpt Test Description',
                    'excerptCategories' => [
                        [
                            'id' => 2,
                            'key' => 'testcat_1',
                            'name' => 'TestCat_1_en',
                            'creator' => 'Adam Ministrator',
                            'changer' => 'Adam Ministrator',
                            'medias' => [
                                [
                                    'id' => 1,
                                    'formatUri' => '/media/1/{format}/media-1.jpg?v=1-0',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            $result->getContent()
        );

        $this->assertSame(
            [1],
            $result->getView()
        );
    }

    public function testResolveDataIsNull(): void
    {
        $locale = 'en';
        $property = $this->prophesize(PropertyInterface::class);

        $result = $this->pageSelectionResolver->resolve(null, $property->reveal(), $locale);

        $this->assertSame([], $result->getContent());

        $this->assertSame([], $result->getView());
    }

    public function testResolveDataIsEmptyArray(): void
    {
        $locale = 'en';
        $property = $this->prophesize(PropertyInterface::class);

        $result = $this->pageSelectionResolver->resolve([], $property->reveal(), $locale);

        $this->assertSame([], $result->getContent());

        $this->assertSame([], $result->getView());
    }
}
