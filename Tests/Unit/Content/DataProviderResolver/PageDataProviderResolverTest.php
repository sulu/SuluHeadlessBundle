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

namespace Sulu\Bundle\HeadlessBundle\Tests\Unit\Content\DataProviderResolver;

use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\HeadlessBundle\Content\DataProviderResolver\PageDataProviderResolver;
use Sulu\Bundle\HeadlessBundle\Content\StructureResolverInterface;
use Sulu\Component\Content\Compat\PropertyParameter;
use Sulu\Component\Content\Compat\StructureInterface;
use Sulu\Component\Content\Mapper\ContentMapperInterface;
use Sulu\Component\Content\Query\ContentQueryBuilderInterface;
use Sulu\Component\Content\SmartContent\PageDataProvider;
use Sulu\Component\SmartContent\Configuration\ProviderConfigurationInterface;
use Sulu\Component\SmartContent\DataProviderResult;
use Sulu\Component\SmartContent\ResourceItemInterface;

class PageDataProviderResolverTest extends TestCase
{
    /**
     * @var PageDataProvider|ObjectProphecy
     */
    private $pageDataProvider;

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
     * @var PageDataProviderResolver
     */
    private $pageResolver;

    protected function setUp(): void
    {
        $this->pageDataProvider = $this->prophesize(PageDataProvider::class);
        $this->structureResolver = $this->prophesize(StructureResolverInterface::class);
        $this->contentQueryBuilder = $this->prophesize(ContentQueryBuilderInterface::class);
        $this->contentMapper = $this->prophesize(ContentMapperInterface::class);

        $this->pageResolver = new PageDataProviderResolver(
            $this->pageDataProvider->reveal(),
            $this->structureResolver->reveal(),
            $this->contentQueryBuilder->reveal(),
            $this->contentMapper->reveal(),
            true
        );
    }

    public function testGetDataProvider(): void
    {
        self::assertSame('pages', $this->pageResolver::getDataProvider());
    }

    public function testGetProviderConfiguration(): void
    {
        $configuration = $this->prophesize(ProviderConfigurationInterface::class);
        $this->pageDataProvider->getConfiguration()->willReturn($configuration->reveal());

        $this->assertSame($configuration->reveal(), $this->pageResolver->getProviderConfiguration());
    }

    public function testGetProviderDefaultParams(): void
    {
        $propertyParameter = $this->prophesize(PropertyParameter::class);
        $this->pageDataProvider->getDefaultPropertyParameter()->willReturn(['test' => $propertyParameter->reveal()]);

        $this->assertSame(['test' => $propertyParameter->reveal()], $this->pageResolver->getProviderDefaultParams());
    }

    public function testResolve(): void
    {
        $providerResultItem1 = $this->prophesize(ResourceItemInterface::class);
        $providerResultItem1->getId()->willReturn('page-id-1');

        $providerResultItem2 = $this->prophesize(ResourceItemInterface::class);
        $providerResultItem2->getId()->willReturn('page-id-2');

        $providerResult = $this->prophesize(DataProviderResult::class);
        $providerResult->getHasNextPage()->willReturn(true);
        $providerResult->getItems()->willReturn([$providerResultItem1->reveal(), $providerResultItem2->reveal()]);

        $propertyParameters = [
            'properties' => new PropertyParameter('properties', [
                new PropertyParameter('contentTitle', 'title'),
                new PropertyParameter('url', 'url'),
                new PropertyParameter('excerptTitle', 'excerpt.title'),
            ]),
        ];

        $this->pageDataProvider->resolveResourceItems(
            ['filter-key' => 'filter-value'],
            $propertyParameters,
            ['webspaceKey' => 'webspace-key', 'locale' => 'en'],
            10,
            1,
            5
        )->willReturn($providerResult->reveal());

        $this->contentQueryBuilder->init([
            'ids' => ['page-id-1', 'page-id-2'],
            'properties' => $propertyParameters['properties']->getValue(),
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
        ]);

        $this->structureResolver->resolveProperties(
            $pageStructure1->reveal(),
            [
                'contentTitle' => 'title',
                'url' => 'url',
                'excerptTitle' => 'excerpt.title',
            ],
            'en'
        )->willReturn([
            'id' => 'page-id-1',
            'template' => 'default',
            'content' => [
                'title' => 'Page Title 1',
                'url' => '/page-url-1',
                'excerptTitle' => 'Page Excerpt Title 1',
            ],
            'view' => [
                'title' => [],
                'excerptTitle' => [],
            ],
        ]);

        $this->structureResolver->resolveProperties(
            $pageStructure2->reveal(),
            [
                'contentTitle' => 'title',
                'url' => 'url',
                'excerptTitle' => 'excerpt.title',
            ],
            'en'
        )->willReturn([
            'id' => 'page-id-2',
            'template' => 'default',
            'content' => [
                'title' => 'Page Title 2',
                'url' => '/page-url-2',
                'excerptTitle' => 'Page Excerpt Title 2',
            ],
            'view' => [
                'title' => [],
                'excerptTitle' => [],
            ],
        ]);

        $result = $this->pageResolver->resolve(
            ['filter-key' => 'filter-value'],
            $propertyParameters,
            ['webspaceKey' => 'webspace-key', 'locale' => 'en'],
            10,
            1,
            5
        );

        $this->assertTrue($result->getHasNextPage());
        $this->assertSame(
            [
                [
                    'id' => 'page-id-1',
                    'template' => 'default',
                    'content' => [
                        'title' => 'Page Title 1',
                        'url' => '/page-url-1',
                        'excerptTitle' => 'Page Excerpt Title 1',
                    ],
                    'view' => [
                        'title' => [],
                        'excerptTitle' => [],
                    ],
                ],
                [
                    'id' => 'page-id-2',
                    'template' => 'default',
                    'content' => [
                        'title' => 'Page Title 2',
                        'url' => '/page-url-2',
                        'excerptTitle' => 'Page Excerpt Title 2',
                    ],
                    'view' => [
                        'title' => [],
                        'excerptTitle' => [],
                    ],
                ],
            ],
            $result->getItems()
        );
    }

    public function testResolveEmptyProviderResult(): void
    {
        $providerResult = $this->prophesize(DataProviderResult::class);
        $providerResult->getHasNextPage()->willReturn(false);
        $providerResult->getItems()->willReturn([]);

        $propertyParameters = [
            'properties' => new PropertyParameter('properties', [
                new PropertyParameter('contentTitle', 'title'),
                new PropertyParameter('excerptTitle', 'excerpt.title'),
            ]),
        ];

        $this->pageDataProvider->resolveResourceItems(
            ['filter-key' => 'filter-value'],
            $propertyParameters,
            ['webspaceKey' => 'webspace-key', 'locale' => 'en'],
            10,
            1,
            5
        )->willReturn($providerResult->reveal());

        $result = $this->pageResolver->resolve(
            ['filter-key' => 'filter-value'],
            $propertyParameters,
            ['webspaceKey' => 'webspace-key', 'locale' => 'en'],
            10,
            1,
            5
        );

        $this->assertFalse($result->getHasNextPage());
        $this->assertSame(
            [],
            $result->getItems()
        );
    }
}
