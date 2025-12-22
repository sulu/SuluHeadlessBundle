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
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\PageSelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\StructureResolverInterface;
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Page\Domain\Model\PageDimensionContentInterface;
use Sulu\Page\Domain\Model\PageInterface;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;

class PageSelectionResolverTest extends TestCase
{
    use ProphecyTrait;

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

    private PageSelectionResolver $pageSelectionResolver;

    private FieldMetadata $fieldMetadata;

    protected function setUp(): void
    {
        $this->structureResolver = $this->prophesize(StructureResolverInterface::class);
        $this->pageRepository = $this->prophesize(PageRepositoryInterface::class);
        $this->contentAggregator = $this->prophesize(ContentAggregatorInterface::class);
        $this->fieldMetadata = new FieldMetadata('pages');

        $this->pageSelectionResolver = new PageSelectionResolver(
            $this->structureResolver->reveal(),
            $this->pageRepository->reveal(),
            $this->contentAggregator->reveal(),
            false, // showDrafts
        );
    }

    public function testGetContentType(): void
    {
        self::assertSame('page_selection', $this->pageSelectionResolver::getContentType());
    }

    public function testResolve(): void
    {
        $locale = 'en';

        $page1 = $this->prophesize(PageInterface::class);
        $page1->getUuid()->willReturn('page-id-1');
        $page2 = $this->prophesize(PageInterface::class);
        $page2->getUuid()->willReturn('page-id-2');

        $dimensionContent1 = $this->prophesize(PageDimensionContentInterface::class);
        $dimensionContent2 = $this->prophesize(PageDimensionContentInterface::class);

        $this->pageRepository->findBy(
            [
                'uuids' => ['page-id-1', 'page-id-2'],
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_LIVE,
            ],
            [],
            [PageRepositoryInterface::GROUP_SELECT_PAGE_WEBSITE => true],
        )->willReturn([$page1->reveal(), $page2->reveal()]);

        $this->contentAggregator->aggregate(
            $page1->reveal(),
            ['locale' => $locale, 'stage' => DimensionContentInterface::STAGE_LIVE],
        )->willReturn($dimensionContent1->reveal());

        $this->contentAggregator->aggregate(
            $page2->reveal(),
            ['locale' => $locale, 'stage' => DimensionContentInterface::STAGE_LIVE],
        )->willReturn($dimensionContent2->reveal());

        $this->structureResolver->resolveProperties(
            $dimensionContent1->reveal(),
            ['title' => 'title', 'url' => 'url'],
            $locale,
        )->willReturn([
            'id' => 'page-id-1',
            'template' => 'default',
            'content' => [
                'title' => 'Page Title 1',
                'url' => '/page-url-1',
            ],
            'view' => [
                'title' => [],
                'url' => [],
            ],
        ]);

        $this->structureResolver->resolveProperties(
            $dimensionContent2->reveal(),
            ['title' => 'title', 'url' => 'url'],
            $locale,
        )->willReturn([
            'id' => 'page-id-2',
            'template' => 'default',
            'content' => [
                'title' => 'Page Title 2',
                'url' => '/page-url-2',
            ],
            'view' => [
                'title' => [],
                'url' => [],
            ],
        ]);

        $result = $this->pageSelectionResolver->resolve(['page-id-1', 'page-id-2'], $this->fieldMetadata, $locale, []);

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame(
            [
                [
                    'id' => 'page-id-1',
                    'template' => 'default',
                    'content' => [
                        'title' => 'Page Title 1',
                        'url' => '/page-url-1',
                    ],
                    'view' => [
                        'title' => [],
                        'url' => [],
                    ],
                ],
                [
                    'id' => 'page-id-2',
                    'template' => 'default',
                    'content' => [
                        'title' => 'Page Title 2',
                        'url' => '/page-url-2',
                    ],
                    'view' => [
                        'title' => [],
                        'url' => [],
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
        $result = $this->pageSelectionResolver->resolve(null, $this->fieldMetadata, 'en', []);

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame([], $result->getContent());
        $this->assertSame(['ids' => []], $result->getView());
    }

    public function testResolveDataIsEmptyArray(): void
    {
        $result = $this->pageSelectionResolver->resolve([], $this->fieldMetadata, 'en', []);

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame([], $result->getContent());
        $this->assertSame(['ids' => []], $result->getView());
    }
}
