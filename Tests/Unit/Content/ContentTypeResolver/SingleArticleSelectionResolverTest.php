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
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\ArticleSelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\SingleArticleSelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;

class SingleArticleSelectionResolverTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var SingleArticleSelectionResolver
     */
    private $singleArticleSelectionResolver;

    /**
     * @var ArticleSelectionResolver|ObjectProphecy
     */
    private $articleSelectionResolver;

    private FieldMetadata $fieldMetadata;

    protected function setUp(): void
    {
        $this->articleSelectionResolver = $this->prophesize(ArticleSelectionResolver::class);
        $this->fieldMetadata = new FieldMetadata('article');

        $this->singleArticleSelectionResolver = new SingleArticleSelectionResolver(
            $this->articleSelectionResolver->reveal()
        );
    }

    public function testGetContentType(): void
    {
        self::assertSame('single_article_selection', $this->singleArticleSelectionResolver::getContentType());
    }

    public function testResolve(): void
    {
        $uuid = '2c55ea29-a5ba-4847-90ce-038b86384ab5';
        $this->articleSelectionResolver->resolve([$uuid], $this->fieldMetadata, 'en', [])->willReturn(
            new ContentView(
                [
                    [
                        'id' => $uuid,
                        'uuid' => $uuid,
                        'title' => 'Test Article',
                        'locale' => 'en',
                        'template' => 'default',
                        'url' => '/test-article',
                    ],
                ],
                [1]
            )
        );

        $result = $this->singleArticleSelectionResolver->resolve($uuid, $this->fieldMetadata, 'en');

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame(
            [
                'id' => $uuid,
                'uuid' => $uuid,
                'title' => 'Test Article',
                'locale' => 'en',
                'template' => 'default',
                'url' => '/test-article',
            ],
            $result->getContent()
        );
        $this->assertSame(
            [
                'id' => $uuid,
            ],
            $result->getView()
        );
    }

    public function testResolveDataIsNull(): void
    {
        $locale = 'en';

        $result = $this->singleArticleSelectionResolver->resolve(null, $this->fieldMetadata, $locale);

        $this->assertNull($result->getContent());

        $this->assertSame(['id' => ''], $result->getView());
    }

    public function testResolveDataIsEmptyString(): void
    {
        $locale = 'en';

        $result = $this->singleArticleSelectionResolver->resolve('', $this->fieldMetadata, $locale);

        $this->assertNull($result->getContent());

        $this->assertSame(['id' => ''], $result->getView());
    }
}
