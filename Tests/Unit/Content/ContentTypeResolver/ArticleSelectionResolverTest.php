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
use Sulu\Article\Domain\Model\Article;
use Sulu\Article\Domain\Model\ArticleDimensionContent;
use Sulu\Article\Domain\Repository\ArticleRepositoryInterface;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\ArticleSelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\StructureResolverInterface;
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;

class ArticleSelectionResolverTest extends TestCase
{
    use ProphecyTrait;

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

    private ArticleSelectionResolver $articleSelectionResolver;

    private FieldMetadata $fieldMetadata;

    protected function setUp(): void
    {
        $this->structureResolver = $this->prophesize(StructureResolverInterface::class);
        $this->articleRepository = $this->prophesize(ArticleRepositoryInterface::class);
        $this->contentAggregator = $this->prophesize(ContentAggregatorInterface::class);
        $this->fieldMetadata = new FieldMetadata('articles');

        $this->articleSelectionResolver = new ArticleSelectionResolver(
            $this->structureResolver->reveal(),
            $this->articleRepository->reveal(),
            $this->contentAggregator->reveal(),
            false,
        );
    }

    public function testGetContentType(): void
    {
        self::assertSame('article_selection', $this->articleSelectionResolver::getContentType());
    }

    public function testResolve(): void
    {
        $locale = 'en';

        $article1 = new Article('article-id-1');
        $article2 = new Article('article-id-2');

        $dimensionContent1 = new ArticleDimensionContent($article1);
        $dimensionContent2 = new ArticleDimensionContent($article2);

        $this->articleRepository->findBy(
            [
                'uuids' => ['article-id-1', 'article-id-2'],
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_LIVE,
            ],
            [],
            [ArticleRepositoryInterface::GROUP_SELECT_ARTICLE_WEBSITE => true],
        )->willReturn([$article1, $article2]);

        $this->contentAggregator->aggregate(
            $article1,
            ['locale' => $locale, 'stage' => DimensionContentInterface::STAGE_LIVE],
        )->willReturn($dimensionContent1);

        $this->contentAggregator->aggregate(
            $article2,
            ['locale' => $locale, 'stage' => DimensionContentInterface::STAGE_LIVE],
        )->willReturn($dimensionContent2);

        $this->structureResolver->resolveProperties(
            $dimensionContent1,
            ['title' => 'title', 'url' => 'url'],
            $locale,
        )->willReturn([
            'id' => 'article-id-1',
            'template' => 'default',
            'content' => [
                'title' => 'Article Title 1',
                'url' => '/article-url-1',
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
            'id' => 'article-id-2',
            'template' => 'default',
            'content' => [
                'title' => 'Article Title 2',
                'url' => '/article-url-2',
            ],
            'view' => [
                'title' => [],
                'url' => [],
            ],
        ]);

        $result = $this->articleSelectionResolver->resolve(['article-id-1', 'article-id-2'], $this->fieldMetadata, $locale, []);

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame(
            [
                [
                    'id' => 'article-id-1',
                    'template' => 'default',
                    'content' => [
                        'title' => 'Article Title 1',
                        'url' => '/article-url-1',
                    ],
                    'view' => [
                        'title' => [],
                        'url' => [],
                    ],
                ],
                [
                    'id' => 'article-id-2',
                    'template' => 'default',
                    'content' => [
                        'title' => 'Article Title 2',
                        'url' => '/article-url-2',
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
            ['ids' => ['article-id-1', 'article-id-2']],
            $result->getView()
        );
    }

    public function testResolveDataIsNull(): void
    {
        $result = $this->articleSelectionResolver->resolve(null, $this->fieldMetadata, 'en', []);

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame([], $result->getContent());
        $this->assertSame(['ids' => []], $result->getView());
    }

    public function testResolveDataIsEmptyArray(): void
    {
        $result = $this->articleSelectionResolver->resolve([], $this->fieldMetadata, 'en', []);

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame([], $result->getContent());
        $this->assertSame(['ids' => []], $result->getView());
    }

    public function testResolveWithShowDrafts(): void
    {
        $locale = 'en';

        $articleSelectionResolver = new ArticleSelectionResolver(
            $this->structureResolver->reveal(),
            $this->articleRepository->reveal(),
            $this->contentAggregator->reveal(),
            true,
        );

        $article1 = new Article('article-id-1');
        $dimensionContent1 = new ArticleDimensionContent($article1);

        $this->articleRepository->findBy(
            [
                'uuids' => ['article-id-1'],
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_DRAFT,
            ],
            [],
            [ArticleRepositoryInterface::GROUP_SELECT_ARTICLE_WEBSITE => true],
        )->willReturn([$article1]);

        $this->contentAggregator->aggregate(
            $article1,
            ['locale' => $locale, 'stage' => DimensionContentInterface::STAGE_DRAFT],
        )->willReturn($dimensionContent1);

        $this->structureResolver->resolveProperties(
            $dimensionContent1,
            ['title' => 'title', 'url' => 'url'],
            $locale,
        )->willReturn([
            'id' => 'article-id-1',
            'template' => 'default',
            'content' => ['title' => 'Article Title 1', 'url' => '/article-url-1'],
            'view' => ['title' => [], 'url' => []],
        ]);

        $result = $articleSelectionResolver->resolve(['article-id-1'], $this->fieldMetadata, $locale, []);

        $this->assertInstanceOf(ContentView::class, $result);
        $content = $result->getContent();
        $this->assertIsArray($content);
        $this->assertCount(1, $content);
    }

    public function testResolveWithCustomProperties(): void
    {
        $locale = 'en';

        $fieldMetadata = new FieldMetadata('articles');
        $fieldMetadata->setType('article_selection');

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

        $article1 = new Article('article-id-1');
        $dimensionContent1 = new ArticleDimensionContent($article1);

        $this->articleRepository->findBy(
            [
                'uuids' => ['article-id-1'],
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_LIVE,
            ],
            [],
            [ArticleRepositoryInterface::GROUP_SELECT_ARTICLE_WEBSITE => true],
        )->willReturn([$article1]);

        $this->contentAggregator->aggregate(
            $article1,
            ['locale' => $locale, 'stage' => DimensionContentInterface::STAGE_LIVE],
        )->willReturn($dimensionContent1);

        $this->structureResolver->resolveProperties(
            $dimensionContent1,
            ['title' => 'title', 'url' => 'url', 'customTitle' => 'title', 'excerpt' => 'excerpt'],
            $locale,
        )->willReturn([
            'id' => 'article-id-1',
            'template' => 'default',
            'content' => ['title' => 'Article Title', 'customTitle' => 'Article Title', 'excerpt' => 'Excerpt'],
            'view' => [],
        ]);

        $result = $this->articleSelectionResolver->resolve(['article-id-1'], $fieldMetadata, $locale, []);

        $this->assertInstanceOf(ContentView::class, $result);
        $content = $result->getContent();
        $this->assertIsArray($content);
        $this->assertCount(1, $content);
    }

    public function testResolveWithNonStringPropertyValue(): void
    {
        $locale = 'en';

        $fieldMetadata = new FieldMetadata('articles');
        $fieldMetadata->setType('article_selection');

        $propertiesOption = new \Sulu\Bundle\AdminBundle\Metadata\FormMetadata\OptionMetadata();
        $propertiesOption->setName('properties');

        $entry = new \Sulu\Bundle\AdminBundle\Metadata\FormMetadata\OptionMetadata();
        $entry->setName('customProp');
        /* @phpstan-ignore argument.type (intentionally testing non-string value) */
        $entry->setValue(['not-a-string']);

        $propertiesOption->setValue([$entry]);
        $fieldMetadata->addOption($propertiesOption);

        $article1 = new Article('article-id-1');
        $dimensionContent1 = new ArticleDimensionContent($article1);

        $this->articleRepository->findBy(
            [
                'uuids' => ['article-id-1'],
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_LIVE,
            ],
            [],
            [ArticleRepositoryInterface::GROUP_SELECT_ARTICLE_WEBSITE => true],
        )->willReturn([$article1]);

        $this->contentAggregator->aggregate(
            $article1,
            ['locale' => $locale, 'stage' => DimensionContentInterface::STAGE_LIVE],
        )->willReturn($dimensionContent1);

        $this->structureResolver->resolveProperties(
            $dimensionContent1,
            ['title' => 'title', 'url' => 'url', 'customProp' => 'customProp'],
            $locale,
        )->willReturn([
            'id' => 'article-id-1',
            'content' => [],
            'view' => [],
        ]);

        $result = $this->articleSelectionResolver->resolve(['article-id-1'], $fieldMetadata, $locale, []);

        $this->assertInstanceOf(ContentView::class, $result);
    }
}
