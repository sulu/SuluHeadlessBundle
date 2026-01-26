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

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\AdminBundle\SmartContent\Configuration\ProviderConfigurationInterface;
use Sulu\Bundle\AdminBundle\SmartContent\SmartContentProviderInterface;
use Sulu\Bundle\HeadlessBundle\Content\DataProviderResolver\SnippetDataProviderResolver;
use Sulu\Bundle\HeadlessBundle\Content\StructureResolverInterface;
use Sulu\Component\Content\Compat\PropertyParameter;
use Sulu\Content\Application\ContentMerger\ContentMergerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Snippet\Domain\Model\SnippetDimensionContent;
use Sulu\Snippet\Domain\Model\SnippetInterface;
use Sulu\Snippet\Domain\Repository\SnippetRepositoryInterface;

class SnippetDataProviderResolverTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var ObjectProphecy<SmartContentProviderInterface>
     */
    private ObjectProphecy $snippetSmartContentProvider;

    /**
     * @var ObjectProphecy<StructureResolverInterface>
     */
    private ObjectProphecy $structureResolver;

    /**
     * @var ObjectProphecy<SnippetRepositoryInterface>
     */
    private ObjectProphecy $snippetRepository;

    /**
     * @var ObjectProphecy<ContentMergerInterface>
     */
    private ObjectProphecy $contentMerger;

    private SnippetDataProviderResolver $snippetDataProviderResolver;

    protected function setUp(): void
    {
        $this->snippetSmartContentProvider = $this->prophesize(SmartContentProviderInterface::class);
        $this->structureResolver = $this->prophesize(StructureResolverInterface::class);
        $this->snippetRepository = $this->prophesize(SnippetRepositoryInterface::class);
        $this->contentMerger = $this->prophesize(ContentMergerInterface::class);

        $this->snippetDataProviderResolver = new SnippetDataProviderResolver(
            $this->snippetSmartContentProvider->reveal(),
            $this->structureResolver->reveal(),
            $this->snippetRepository->reveal(),
            $this->contentMerger->reveal(),
        );
    }

    public function testGetDataProvider(): void
    {
        self::assertSame('snippets', $this->snippetDataProviderResolver::getDataProvider());
    }

    public function testGetProviderConfiguration(): void
    {
        $configuration = $this->prophesize(ProviderConfigurationInterface::class);
        $this->snippetSmartContentProvider->getConfiguration()->willReturn($configuration->reveal());

        $this->assertSame($configuration->reveal(), $this->snippetDataProviderResolver->getProviderConfiguration());
    }

    public function testGetProviderDefaultParams(): void
    {
        $this->assertSame([], $this->snippetDataProviderResolver->getProviderDefaultParams());
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
        $this->snippetSmartContentProvider->findFlatBy(
            Argument::type('array'),
            Argument::type('array'),
            ['webspaceKey' => 'webspace-key', 'locale' => 'en']
        )->willReturn([
            ['id' => 'snippet-id-1', 'title' => 'Snippet 1'],
            ['id' => 'snippet-id-2', 'title' => 'Snippet 2'],
        ]);

        // Create mock snippets with dimension contents
        $snippet1 = $this->prophesize(SnippetInterface::class);
        $snippet1->getUuid()->willReturn('snippet-id-1');
        $dimensionContent1 = $this->prophesize(SnippetDimensionContent::class);
        $snippet1->getDimensionContents()->willReturn(new ArrayCollection([$dimensionContent1->reveal()]));

        $snippet2 = $this->prophesize(SnippetInterface::class);
        $snippet2->getUuid()->willReturn('snippet-id-2');
        $dimensionContent2 = $this->prophesize(SnippetDimensionContent::class);
        $snippet2->getDimensionContents()->willReturn(new ArrayCollection([$dimensionContent2->reveal()]));

        $this->snippetRepository->findBy(
            Argument::type('array'),
            [],
            [SnippetRepositoryInterface::GROUP_SELECT_SNIPPET_WEBSITE => true]
        )->willReturn([$snippet1->reveal(), $snippet2->reveal()]);

        // Content merger returns merged dimension content
        $mergedContent1 = $this->prophesize(DimensionContentInterface::class);
        $mergedContent2 = $this->prophesize(DimensionContentInterface::class);

        $this->contentMerger->merge(Argument::that(static function ($collection) {
            return true; // Accept any DimensionContentCollection
        }))->willReturn($mergedContent1->reveal(), $mergedContent2->reveal());

        $this->structureResolver->resolveProperties(
            $mergedContent1,
            [
                'title' => 'title',
                'contentDescription' => 'description',
                'excerptTitle' => 'excerpt.title',
            ],
            'en'
        )->willReturn([
            'id' => 'snippet-id-1',
            'template' => 'default',
            'content' => [
                'title' => 'Snippet Title 1',
                'contentDescription' => 'Snippet Content Description',
                'excerptTitle' => 'Snippet Excerpt Title 1',
            ],
            'view' => [],
        ]);

        $this->structureResolver->resolveProperties(
            $mergedContent2,
            [
                'title' => 'title',
                'contentDescription' => 'description',
                'excerptTitle' => 'excerpt.title',
            ],
            'en'
        )->willReturn([
            'id' => 'snippet-id-2',
            'template' => 'default',
            'content' => [
                'title' => 'Snippet Title 2',
                'contentDescription' => 'Snippet Content Description',
                'excerptTitle' => 'Snippet Excerpt Title 2',
            ],
            'view' => [],
        ]);

        $result = $this->snippetDataProviderResolver->resolve(
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

        $this->snippetSmartContentProvider->findFlatBy(
            Argument::type('array'),
            Argument::type('array'),
            ['webspaceKey' => 'webspace-key', 'locale' => 'en']
        )->willReturn([]);

        $result = $this->snippetDataProviderResolver->resolve(
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
