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
use Sulu\Page\Domain\Model\Page;
use Sulu\Page\Domain\Model\PageDimensionContent;
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

        $page1 = new Page('page-id-1');
        $page2 = new Page('page-id-2');

        $dimensionContent1 = new PageDimensionContent($page1);
        $dimensionContent2 = new PageDimensionContent($page2);

        $this->pageRepository->findBy(
            [
                'uuids' => ['page-id-1', 'page-id-2'],
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_LIVE,
            ],
            [],
            [PageRepositoryInterface::GROUP_SELECT_PAGE_WEBSITE => true],
        )->willReturn([$page1, $page2]);

        $this->contentAggregator->aggregate(
            $page1,
            ['locale' => $locale, 'stage' => DimensionContentInterface::STAGE_LIVE],
        )->willReturn($dimensionContent1);

        $this->contentAggregator->aggregate(
            $page2,
            ['locale' => $locale, 'stage' => DimensionContentInterface::STAGE_LIVE],
        )->willReturn($dimensionContent2);

        $this->structureResolver->resolveProperties(
            $dimensionContent1,
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
            $dimensionContent2,
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

    public function testResolveWithShowDrafts(): void
    {
        $locale = 'en';

        $pageSelectionResolver = new PageSelectionResolver(
            $this->structureResolver->reveal(),
            $this->pageRepository->reveal(),
            $this->contentAggregator->reveal(),
            true,
        );

        $page1 = new Page('page-id-1');
        $dimensionContent1 = new PageDimensionContent($page1);

        $this->pageRepository->findBy(
            [
                'uuids' => ['page-id-1'],
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_DRAFT,
            ],
            [],
            [PageRepositoryInterface::GROUP_SELECT_PAGE_WEBSITE => true],
        )->willReturn([$page1]);

        $this->contentAggregator->aggregate(
            $page1,
            ['locale' => $locale, 'stage' => DimensionContentInterface::STAGE_DRAFT],
        )->willReturn($dimensionContent1);

        $this->structureResolver->resolveProperties(
            $dimensionContent1,
            ['title' => 'title', 'url' => 'url'],
            $locale,
        )->willReturn([
            'id' => 'page-id-1',
            'template' => 'default',
            'content' => ['title' => 'Page Title 1', 'url' => '/page-url-1'],
            'view' => ['title' => [], 'url' => []],
        ]);

        $result = $pageSelectionResolver->resolve(['page-id-1'], $this->fieldMetadata, $locale, []);

        $this->assertInstanceOf(ContentView::class, $result);
        $content = $result->getContent();
        $this->assertIsArray($content);
        $this->assertCount(1, $content);
    }

    public function testResolveWithCustomProperties(): void
    {
        $locale = 'en';

        $fieldMetadata = new FieldMetadata('pages');
        $fieldMetadata->setType('page_selection');

        $propertiesOption = new \Sulu\Bundle\AdminBundle\Metadata\FormMetadata\OptionMetadata();
        $propertiesOption->setName('properties');

        $titleEntry = new \Sulu\Bundle\AdminBundle\Metadata\FormMetadata\OptionMetadata();
        $titleEntry->setName('customTitle');
        $titleEntry->setValue('title');

        $excerptEntry = new \Sulu\Bundle\AdminBundle\Metadata\FormMetadata\OptionMetadata();
        $excerptEntry->setName('excerpt');
        $excerptEntry->setValue(null);

        $propertiesOption->setValue([$titleEntry, $excerptEntry]);
        $fieldMetadata->addOption($propertiesOption);

        $page1 = new Page('page-id-1');
        $dimensionContent1 = new PageDimensionContent($page1);

        $this->pageRepository->findBy(
            [
                'uuids' => ['page-id-1'],
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_LIVE,
            ],
            [],
            [PageRepositoryInterface::GROUP_SELECT_PAGE_WEBSITE => true],
        )->willReturn([$page1]);

        $this->contentAggregator->aggregate(
            $page1,
            ['locale' => $locale, 'stage' => DimensionContentInterface::STAGE_LIVE],
        )->willReturn($dimensionContent1);

        $this->structureResolver->resolveProperties(
            $dimensionContent1,
            ['title' => 'title', 'url' => 'url', 'customTitle' => 'title', 'excerpt' => 'excerpt'],
            $locale,
        )->willReturn([
            'id' => 'page-id-1',
            'template' => 'default',
            'content' => ['title' => 'Page Title', 'customTitle' => 'Page Title', 'excerpt' => 'Excerpt'],
            'view' => [],
        ]);

        $result = $this->pageSelectionResolver->resolve(['page-id-1'], $fieldMetadata, $locale, []);

        $this->assertInstanceOf(ContentView::class, $result);
        $content = $result->getContent();
        $this->assertIsArray($content);
        $this->assertCount(1, $content);
    }

    public function testResolveWithNonStringPropertyValue(): void
    {
        $locale = 'en';

        $fieldMetadata = new FieldMetadata('pages');
        $fieldMetadata->setType('page_selection');

        $propertiesOption = new \Sulu\Bundle\AdminBundle\Metadata\FormMetadata\OptionMetadata();
        $propertiesOption->setName('properties');

        $entry = new \Sulu\Bundle\AdminBundle\Metadata\FormMetadata\OptionMetadata();
        $entry->setName('customProp');
        /* @phpstan-ignore argument.type (intentionally testing non-string value) */
        $entry->setValue(['not-a-string']);

        $propertiesOption->setValue([$entry]);
        $fieldMetadata->addOption($propertiesOption);

        $page1 = new Page('page-id-1');
        $dimensionContent1 = new PageDimensionContent($page1);

        $this->pageRepository->findBy(
            [
                'uuids' => ['page-id-1'],
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_LIVE,
            ],
            [],
            [PageRepositoryInterface::GROUP_SELECT_PAGE_WEBSITE => true],
        )->willReturn([$page1]);

        $this->contentAggregator->aggregate(
            $page1,
            ['locale' => $locale, 'stage' => DimensionContentInterface::STAGE_LIVE],
        )->willReturn($dimensionContent1);

        $this->structureResolver->resolveProperties(
            $dimensionContent1,
            ['title' => 'title', 'url' => 'url', 'customProp' => 'customProp'],
            $locale,
        )->willReturn([
            'id' => 'page-id-1',
            'content' => [],
            'view' => [],
        ]);

        $result = $this->pageSelectionResolver->resolve(['page-id-1'], $fieldMetadata, $locale, []);

        $this->assertInstanceOf(ContentView::class, $result);
    }
}
