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
use Sulu\Bundle\HeadlessBundle\Content\DataProviderResolver\SnippetDataProviderResolver;
use Sulu\Bundle\HeadlessBundle\Content\StructureResolverInterface;
use Sulu\Component\Content\Compat\PropertyParameter;
use Sulu\Component\Content\Compat\StructureInterface;
use Sulu\Component\Content\Mapper\ContentMapperInterface;
use Sulu\Component\Content\Query\ContentQueryBuilderInterface;
use Sulu\Component\SmartContent\Configuration\ProviderConfigurationInterface;
use Sulu\Component\SmartContent\DataProviderInterface;
use Sulu\Component\SmartContent\DataProviderResult;
use Sulu\Component\SmartContent\ResourceItemInterface;

class SnippetDataProviderResolverTest extends TestCase
{
    /**
     * @var DataProviderInterface|ObjectProphecy
     */
    private $snippetDataProvider;

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
     * @var SnippetDataProviderResolver
     */
    private $snippetDataProviderResolver;

    protected function setUp(): void
    {
        $this->snippetDataProvider = $this->prophesize(DataProviderInterface::class);
        $this->structureResolver = $this->prophesize(StructureResolverInterface::class);
        $this->contentQueryBuilder = $this->prophesize(ContentQueryBuilderInterface::class);
        $this->contentMapper = $this->prophesize(ContentMapperInterface::class);

        $this->snippetDataProviderResolver = new SnippetDataProviderResolver(
            $this->snippetDataProvider->reveal(),
            $this->structureResolver->reveal(),
            $this->contentQueryBuilder->reveal(),
            $this->contentMapper->reveal()
        );
    }

    public function testGetDataProvider(): void
    {
        self::assertSame('snippets', $this->snippetDataProviderResolver::getDataProvider());
    }

    public function testGetProviderConfiguration(): void
    {
        $configuration = $this->prophesize(ProviderConfigurationInterface::class);
        $this->snippetDataProvider->getConfiguration()->willReturn($configuration->reveal());

        $this->assertSame($configuration->reveal(), $this->snippetDataProviderResolver->getProviderConfiguration());
    }

    public function testGetProviderDefaultParams(): void
    {
        $propertyParameter = $this->prophesize(PropertyParameter::class);
        $this->snippetDataProvider->getDefaultPropertyParameter()->willReturn(['test' => $propertyParameter->reveal()]);

        $this->assertSame(['test' => $propertyParameter->reveal()], $this->snippetDataProviderResolver->getProviderDefaultParams());
    }

    public function testResolve(): void
    {
        $providerResultItem1 = $this->prophesize(ResourceItemInterface::class);
        $providerResultItem1->getId()->willReturn('snippet-id-1');

        $providerResultItem2 = $this->prophesize(ResourceItemInterface::class);
        $providerResultItem2->getId()->willReturn('snippet-id-2');

        $providerResult = $this->prophesize(DataProviderResult::class);
        $providerResult->getHasNextPage()->willReturn(true);
        $providerResult->getItems()->willReturn([$providerResultItem1->reveal(), $providerResultItem2->reveal()]);

        $propertyParameters = [
            'properties' => new PropertyParameter('properties', [
                new PropertyParameter('contentDescription', 'description'),
                new PropertyParameter('excerptTitle', 'excerpt.title'),
            ]),
        ];

        // expected and unexpected service calls
        $this->snippetDataProvider->resolveResourceItems(
            ['filter-key' => 'filter-value'],
            $propertyParameters,
            ['webspaceKey' => 'webspace-key', 'locale' => 'en'],
            10,
            1,
            5
        )->willReturn($providerResult->reveal())->shouldBeCalledOnce();

        $this->contentQueryBuilder->init([
            'ids' => ['snippet-id-1', 'snippet-id-2'],
            'properties' => $propertyParameters['properties']->getValue(),
        ])->shouldBeCalled();
        $this->contentQueryBuilder->build('webspace-key', ['en'])->willReturn(['snippet-query-string']);

        $snippetStructure1 = $this->prophesize(StructureInterface::class);
        $snippetStructure1->getUuid()->willReturn('snippet-id-1');
        $snippetStructure2 = $this->prophesize(StructureInterface::class);
        $snippetStructure2->getUuid()->willReturn('snippet-id-2');
        $this->contentMapper->loadBySql2(
            'snippet-query-string',
            'en',
            'webspace-key'
        )->willReturn([
            $snippetStructure2->reveal(),
            $snippetStructure1->reveal(),
        ])->shouldBeCalledOnce();

        $this->structureResolver->resolveProperties(
            $snippetStructure1->reveal(),
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
            'view' => [
                'title' => [],
                'contentDescription' => [],
                'excerptTitle' => [],
            ],
        ])->shouldBeCalledOnce();

        $this->structureResolver->resolveProperties(
            $snippetStructure2->reveal(),
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
            'view' => [
                'title' => [],
                'contentDescription' => [],
                'excerptTitle' => [],
            ],
        ])->shouldBeCalledOnce();

        // call test function
        $result = $this->snippetDataProviderResolver->resolve(
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
                    'id' => 'snippet-id-1',
                    'template' => 'default',
                    'content' => [
                        'title' => 'Snippet Title 1',
                        'contentDescription' => 'Snippet Content Description',
                        'excerptTitle' => 'Snippet Excerpt Title 1',
                    ],
                    'view' => [
                        'title' => [],
                        'contentDescription' => [],
                        'excerptTitle' => [],
                    ],
                ],
                [
                    'id' => 'snippet-id-2',
                    'template' => 'default',
                    'content' => [
                        'title' => 'Snippet Title 2',
                        'contentDescription' => 'Snippet Content Description',
                        'excerptTitle' => 'Snippet Excerpt Title 2',
                    ],
                    'view' => [
                        'title' => [],
                        'contentDescription' => [],
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
                new PropertyParameter('contentDescription', 'description'),
                new PropertyParameter('excerptTitle', 'excerpt.title'),
            ]),
        ];

        // expected and unexpected service calls
        $this->snippetDataProvider->resolveResourceItems(
            ['filter-key' => 'filter-value'],
            $propertyParameters,
            ['webspaceKey' => 'webspace-key', 'locale' => 'en'],
            10,
            1,
            5
        )->willReturn($providerResult->reveal())
            ->shouldBeCalledOnce();

        // call test function
        $result = $this->snippetDataProviderResolver->resolve(
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
