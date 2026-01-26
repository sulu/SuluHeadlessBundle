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
use Sulu\Article\Domain\Model\ArticleInterface;
use Sulu\Article\Domain\Repository\ArticleRepositoryInterface;
use Sulu\Bundle\AdminBundle\SmartContent\Configuration\ProviderConfigurationInterface;
use Sulu\Bundle\AdminBundle\SmartContent\SmartContentProviderInterface;
use Sulu\Bundle\HeadlessBundle\Content\DataProviderResolver\ArticleDataProviderResolver;
use Sulu\Bundle\HeadlessBundle\Content\StructureResolverInterface;
use Sulu\Component\Content\Compat\PropertyParameter;
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;

class ArticleDataProviderResolverTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var ObjectProphecy<SmartContentProviderInterface>
     */
    private ObjectProphecy $articleSmartContentProvider;

    /**
     * @var ObjectProphecy<StructureResolverInterface>
     */
    private ObjectProphecy $structureResolver;

    /**
     * @var ObjectProphecy<ArticleRepositoryInterface>
     */
    private ObjectProphecy $articleRepository;

    /**
     * @var ObjectProphecy<ContentAggregatorInterface>
     */
    private ObjectProphecy $contentAggregator;

    private ArticleDataProviderResolver $articleDataProviderResolver;

    protected function setUp(): void
    {
        $this->articleSmartContentProvider = $this->prophesize(SmartContentProviderInterface::class);
        $this->structureResolver = $this->prophesize(StructureResolverInterface::class);
        $this->articleRepository = $this->prophesize(ArticleRepositoryInterface::class);
        $this->contentAggregator = $this->prophesize(ContentAggregatorInterface::class);

        $this->articleDataProviderResolver = new ArticleDataProviderResolver(
            $this->articleSmartContentProvider->reveal(),
            $this->structureResolver->reveal(),
            $this->articleRepository->reveal(),
            $this->contentAggregator->reveal(),
            true,
        );
    }

    public function testGetDataProvider(): void
    {
        self::assertSame('articles', $this->articleDataProviderResolver::getDataProvider());
    }

    public function testGetProviderConfiguration(): void
    {
        $configuration = $this->prophesize(ProviderConfigurationInterface::class);
        $this->articleSmartContentProvider->getConfiguration()->willReturn($configuration->reveal());

        $this->assertSame($configuration->reveal(), $this->articleDataProviderResolver->getProviderConfiguration());
    }

    public function testGetProviderDefaultParams(): void
    {
        $this->assertSame([], $this->articleDataProviderResolver->getProviderDefaultParams());
    }

    public function testResolve(): void
    {
        $propertyParameters = [
            'properties' => new PropertyParameter('properties', [
                new PropertyParameter('contentDescription', 'description'),
                new PropertyParameter('excerptTitle', 'excerpt.title'),
            ]),
        ];

        $this->articleSmartContentProvider->findFlatBy(
            Argument::type('array'),
            Argument::type('array'),
            ['webspaceKey' => 'webspace-key', 'locale' => 'en']
        )->willReturn([
            ['id' => 'article-id-1', 'title' => 'Article 1'],
            ['id' => 'article-id-2', 'title' => 'Article 2'],
        ]);

        $article1 = $this->prophesize(ArticleInterface::class);
        $article1->getUuid()->willReturn('article-id-1');

        $article2 = $this->prophesize(ArticleInterface::class);
        $article2->getUuid()->willReturn('article-id-2');

        $this->articleRepository->findBy(
            Argument::type('array'),
            [],
            [ArticleRepositoryInterface::GROUP_SELECT_ARTICLE_WEBSITE => true]
        )->willReturn([$article1->reveal(), $article2->reveal()]);

        $mergedContent1 = $this->prophesize(DimensionContentInterface::class);
        $mergedContent2 = $this->prophesize(DimensionContentInterface::class);

        $this->contentAggregator->aggregate(
            $article1,
            ['locale' => 'en', 'stage' => 'draft']
        )->willReturn($mergedContent1->reveal());

        $this->contentAggregator->aggregate(
            $article2,
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
            'id' => 'article-id-1',
            'template' => 'default',
            'content' => [
                'title' => 'Article Title 1',
                'url' => '/article-url-1',
                'contentDescription' => 'Article Content Description',
                'excerptTitle' => 'Article Excerpt Title 1',
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
            'id' => 'article-id-2',
            'template' => 'default',
            'content' => [
                'title' => 'Article Title 2',
                'url' => '/article-url-2',
                'contentDescription' => 'Article Content Description',
                'excerptTitle' => 'Article Excerpt Title 2',
            ],
            'view' => [],
        ]);

        $result = $this->articleDataProviderResolver->resolve(
            ['filter-key' => 'filter-value'],
            $propertyParameters,
            ['webspaceKey' => 'webspace-key', 'locale' => 'en'],
            10,
            1,
            5
        );

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

        $this->articleSmartContentProvider->findFlatBy(
            Argument::type('array'),
            Argument::type('array'),
            ['webspaceKey' => 'webspace-key', 'locale' => 'en']
        )->willReturn([]);

        $result = $this->articleDataProviderResolver->resolve(
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
