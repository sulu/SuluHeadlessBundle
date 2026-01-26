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
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\AdminBundle\SmartContent\Configuration\ProviderConfigurationInterface;
use Sulu\Bundle\AdminBundle\SmartContent\SmartContentProviderInterface;
use Sulu\Bundle\HeadlessBundle\Content\DataProviderResolver\PageDataProviderResolver;
use Sulu\Bundle\HeadlessBundle\Content\StructureResolverInterface;
use Sulu\Component\Content\Compat\PropertyParameter;
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Page\Domain\Model\PageInterface;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;

class PageDataProviderResolverTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var ObjectProphecy<SmartContentProviderInterface>
     */
    private ObjectProphecy $pageSmartContentProvider;

    /**
     * @var ObjectProphecy<StructureResolverInterface>
     */
    private ObjectProphecy $structureResolver;

    /**
     * @var ObjectProphecy<PageRepositoryInterface>
     */
    private ObjectProphecy $pageRepository;

    /**
     * @var ObjectProphecy<ContentAggregatorInterface>
     */
    private ObjectProphecy $contentAggregator;

    private PageDataProviderResolver $pageDataProviderResolver;

    protected function setUp(): void
    {
        $this->pageSmartContentProvider = $this->prophesize(SmartContentProviderInterface::class);
        $this->structureResolver = $this->prophesize(StructureResolverInterface::class);
        $this->pageRepository = $this->prophesize(PageRepositoryInterface::class);
        $this->contentAggregator = $this->prophesize(ContentAggregatorInterface::class);

        $this->pageDataProviderResolver = new PageDataProviderResolver(
            $this->pageSmartContentProvider->reveal(),
            $this->structureResolver->reveal(),
            $this->pageRepository->reveal(),
            $this->contentAggregator->reveal(),
            true, // showDrafts
        );
    }

    public function testGetDataProvider(): void
    {
        self::assertSame('pages', $this->pageDataProviderResolver::getDataProvider());
    }

    public function testGetProviderConfiguration(): void
    {
        $configuration = $this->prophesize(ProviderConfigurationInterface::class);
        $this->pageSmartContentProvider->getConfiguration()->willReturn($configuration->reveal());

        $this->assertSame($configuration->reveal(), $this->pageDataProviderResolver->getProviderConfiguration());
    }

    public function testGetProviderDefaultParams(): void
    {
        $this->assertSame([], $this->pageDataProviderResolver->getProviderDefaultParams());
    }

    public function testResolve(): void
    {
        $propertyParameters = [
            'properties' => new PropertyParameter('properties', [
                new PropertyParameter('contentDescription', 'description'),
                new PropertyParameter('excerptTitle', 'excerpt.title'),
            ]),
        ];

        // SmartContentProvider returns flat results
        $this->pageSmartContentProvider->findFlatBy(
            Argument::type('array'),
            Argument::type('array'),
            ['webspaceKey' => 'webspace-key', 'locale' => 'en']
        )->willReturn([
            ['id' => 'page-id-1', 'title' => 'Page 1'],
            ['id' => 'page-id-2', 'title' => 'Page 2'],
        ]);

        // Create mock pages
        $page1 = $this->prophesize(PageInterface::class);
        $page1->getUuid()->willReturn('page-id-1');

        $page2 = $this->prophesize(PageInterface::class);
        $page2->getUuid()->willReturn('page-id-2');

        $this->pageRepository->findBy(
            Argument::type('array'),
            [],
            [PageRepositoryInterface::GROUP_SELECT_PAGE_WEBSITE => true]
        )->willReturn([$page1->reveal(), $page2->reveal()]);

        // Content aggregator returns merged dimension content
        $mergedContent1 = $this->prophesize(DimensionContentInterface::class);
        $mergedContent2 = $this->prophesize(DimensionContentInterface::class);

        $this->contentAggregator->aggregate(
            $page1,
            ['locale' => 'en', 'stage' => 'draft']
        )->willReturn($mergedContent1->reveal());

        $this->contentAggregator->aggregate(
            $page2,
            ['locale' => 'en', 'stage' => 'draft']
        )->willReturn($mergedContent2->reveal());

        $this->structureResolver->resolveProperties(
            $mergedContent1,
            [
                'title' => 'title',
                'url' => 'url',
                'contentDescription' => 'description',
                'excerptTitle' => 'excerpt.title',
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
            ],
            'view' => [],
        ]);

        $this->structureResolver->resolveProperties(
            $mergedContent2,
            [
                'title' => 'title',
                'url' => 'url',
                'contentDescription' => 'description',
                'excerptTitle' => 'excerpt.title',
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
            ],
            'view' => [],
        ]);

        $result = $this->pageDataProviderResolver->resolve(
            ['filter-key' => 'filter-value'],
            $propertyParameters,
            ['webspaceKey' => 'webspace-key', 'locale' => 'en'],
            10,
            1,
            5
        );

        // hasNextPage is false because count(2) < pageSize(5)
        $this->assertFalse($result->getHasNextPage());
        $this->assertCount(2, $result->getItems());
    }

    public function testResolveEmptyProviderResult(): void
    {
        $propertyParameters = [
            'properties' => new PropertyParameter('properties', [
                new PropertyParameter('contentDescription', 'description'),
            ]),
        ];

        $this->pageSmartContentProvider->findFlatBy(
            Argument::type('array'),
            Argument::type('array'),
            ['webspaceKey' => 'webspace-key', 'locale' => 'en']
        )->willReturn([]);

        $result = $this->pageDataProviderResolver->resolve(
            ['filter-key' => 'filter-value'],
            $propertyParameters,
            ['webspaceKey' => 'webspace-key', 'locale' => 'en'],
            10,
            1,
            5
        );

        $this->assertFalse($result->getHasNextPage());
        $this->assertSame([], $result->getItems());
    }
}
