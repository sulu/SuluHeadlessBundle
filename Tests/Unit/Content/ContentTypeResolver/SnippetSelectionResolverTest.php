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
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\SnippetSelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\StructureResolverInterface;
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Snippet\Domain\Model\SnippetAreaInterface;
use Sulu\Snippet\Domain\Model\SnippetDimensionContentInterface;
use Sulu\Snippet\Domain\Model\SnippetInterface;
use Sulu\Snippet\Domain\Repository\SnippetAreaRepositoryInterface;
use Sulu\Snippet\Domain\Repository\SnippetRepositoryInterface;

class SnippetSelectionResolverTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var ObjectProphecy<SnippetRepositoryInterface>
     */
    private ObjectProphecy $snippetRepository;

    /**
     * @var ObjectProphecy<StructureResolverInterface>
     */
    private ObjectProphecy $structureResolver;

    /**
     * @var ObjectProphecy<ContentAggregatorInterface>
     */
    private ObjectProphecy $contentAggregator;

    /**
     * @var ObjectProphecy<SnippetAreaRepositoryInterface>
     */
    private ObjectProphecy $snippetAreaRepository;

    private SnippetSelectionResolver $snippetSelectionResolver;

    private FieldMetadata $fieldMetadata;

    protected function setUp(): void
    {
        $this->snippetRepository = $this->prophesize(SnippetRepositoryInterface::class);
        $this->structureResolver = $this->prophesize(StructureResolverInterface::class);
        $this->contentAggregator = $this->prophesize(ContentAggregatorInterface::class);
        $this->snippetAreaRepository = $this->prophesize(SnippetAreaRepositoryInterface::class);
        $this->fieldMetadata = new FieldMetadata('snippets');

        $this->snippetSelectionResolver = new SnippetSelectionResolver(
            $this->snippetRepository->reveal(),
            $this->structureResolver->reveal(),
            $this->contentAggregator->reveal(),
            $this->snippetAreaRepository->reveal(),
        );
    }

    public function testGetContentType(): void
    {
        self::assertSame('snippet_selection', $this->snippetSelectionResolver::getContentType());
    }

    public function testResolve(): void
    {
        $locale = 'en';

        $snippet1 = $this->prophesize(SnippetInterface::class);
        $snippet2 = $this->prophesize(SnippetInterface::class);

        $dimensionContent1 = $this->prophesize(SnippetDimensionContentInterface::class);
        $dimensionContent2 = $this->prophesize(SnippetDimensionContentInterface::class);

        $this->snippetRepository->findBy(
            [
                'uuids' => ['snippet-1', 'snippet-2'],
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_LIVE,
                'load_ghost_content' => true,
            ],
            [],
            [SnippetRepositoryInterface::GROUP_SELECT_SNIPPET_WEBSITE => true],
        )->willReturn([$snippet1->reveal(), $snippet2->reveal()]);

        $this->contentAggregator->aggregate(
            $snippet1->reveal(),
            ['locale' => $locale, 'stage' => DimensionContentInterface::STAGE_LIVE],
        )->willReturn($dimensionContent1->reveal());

        $this->contentAggregator->aggregate(
            $snippet2->reveal(),
            ['locale' => $locale, 'stage' => DimensionContentInterface::STAGE_LIVE],
        )->willReturn($dimensionContent2->reveal());

        $this->structureResolver->resolve($dimensionContent1->reveal(), $locale, false)->willReturn([
            'id' => 'snippet-1',
            'template' => 'test',
            'content' => [],
            'view' => [],
        ]);

        $this->structureResolver->resolve($dimensionContent2->reveal(), $locale, false)->willReturn([
            'id' => 'snippet-2',
            'template' => 'test',
            'content' => [],
            'view' => [],
        ]);

        $result = $this->snippetSelectionResolver->resolve(['snippet-1', 'snippet-2'], $this->fieldMetadata, $locale, []);

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame(
            [
                [
                    'id' => 'snippet-1',
                    'template' => 'test',
                    'content' => [],
                    'view' => [],
                ],
                [
                    'id' => 'snippet-2',
                    'template' => 'test',
                    'content' => [],
                    'view' => [],
                ],
            ],
            $result->getContent()
        );

        $this->assertSame(
            ['ids' => ['snippet-1', 'snippet-2']],
            $result->getView()
        );
    }

    public function testResolveDataIsNull(): void
    {
        $result = $this->snippetSelectionResolver->resolve(null, $this->fieldMetadata, 'en', []);

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame([], $result->getContent());
        $this->assertSame(['ids' => []], $result->getView());
    }

    public function testResolveDataIsEmptyArray(): void
    {
        $result = $this->snippetSelectionResolver->resolve([], $this->fieldMetadata, 'en', []);

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame([], $result->getContent());
        $this->assertSame(['ids' => []], $result->getView());
    }

    public function testResolveDataIsNullWithDefaultArea(): void
    {
        $locale = 'en';
        $webspaceKey = 'sulu_io';

        $defaultSnippet = $this->prophesize(SnippetInterface::class);
        $defaultSnippet->getUuid()->willReturn('default-snippet-1');

        $snippetArea = $this->prophesize(SnippetAreaInterface::class);
        $snippetArea->getSnippet()->willReturn($defaultSnippet->reveal());

        $this->snippetAreaRepository->findOneBy([
            'webspaceKey' => $webspaceKey,
            'areaKey' => 'default_area',
        ])->willReturn($snippetArea->reveal());

        $dimensionContent = $this->prophesize(SnippetDimensionContentInterface::class);

        $this->snippetRepository->findBy(
            [
                'uuids' => ['default-snippet-1'],
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_LIVE,
                'load_ghost_content' => true,
            ],
            [],
            [SnippetRepositoryInterface::GROUP_SELECT_SNIPPET_WEBSITE => true],
        )->willReturn([$defaultSnippet->reveal()]);

        $this->contentAggregator->aggregate(
            $defaultSnippet->reveal(),
            ['locale' => $locale, 'stage' => DimensionContentInterface::STAGE_LIVE],
        )->willReturn($dimensionContent->reveal());

        $this->structureResolver->resolve($dimensionContent->reveal(), $locale, false)->willReturn([
            'id' => 'default-snippet-1',
            'template' => 'test',
            'content' => [],
            'view' => [],
        ]);

        // Create field metadata with default option
        $fieldMetadata = new FieldMetadata('snippets');
        $option = new \Sulu\Bundle\AdminBundle\Metadata\FormMetadata\OptionMetadata();
        $option->setName('default');
        $option->setValue('default_area');
        $fieldMetadata->addOption($option);

        $result = $this->snippetSelectionResolver->resolve(null, $fieldMetadata, $locale, ['webspaceKey' => $webspaceKey]);

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame(
            [
                [
                    'id' => 'default-snippet-1',
                    'template' => 'test',
                    'content' => [],
                    'view' => [],
                ],
            ],
            $result->getContent()
        );

        $this->assertSame(
            ['ids' => ['default-snippet-1']],
            $result->getView()
        );
    }
}
