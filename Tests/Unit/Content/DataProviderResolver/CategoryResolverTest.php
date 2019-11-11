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

use JMS\Serializer\SerializationContext;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\CategoryBundle\Api\Category;
use Sulu\Bundle\CategoryBundle\Category\CategoryManagerInterface;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\CategorySelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\CategorySerializer;
use Sulu\Bundle\MediaBundle\Api\Media;
use Sulu\Component\Content\Compat\PropertyInterface;

class CategoryResolverTest extends TestCase
{
    /**
     * @var CategoryManagerInterface|ObjectProphecy
     */
    private $categoryManager;

    /**
     * @var CategorySerializer|ObjectProphecy
     */
    private $categorySerializer;

    /**
     * @var CategorySelectionResolver
     */
    private $categorySelectionResolver;

    protected function setUp(): void
    {
        $this->categoryManager = $this->prophesize(CategoryManagerInterface::class);
        $this->categorySerializer = $this->prophesize(CategorySerializer::class);

        $this->categorySelectionResolver = new CategorySelectionResolver(
            $this->categoryManager->reveal(),
            $this->categorySerializer->reveal()
        );
    }

    public function testGetContentType(): void
    {
        self::assertSame('category_selection', $this->categorySelectionResolver::getContentType());
    }

    /**
     * @dataProvider categoryResolverProvider
     *
     * @param mixed[] $data
     * @param mixed[] $view
     */
    public function testResolve(array $data, array $view): void
    {
        $locale = 'en';

        $category = $this->prophesize(Category::class);
        $category->getId()->willReturn(1);
        $category->getLocale()->willReturn('en');
        $category->getKey()->willReturn('key-1');
        $category->getName()->willReturn('cat-1');
        $category->getDescription()->willReturn('desc-1');

        $media = $this->prophesize(Media::class);
        $media->getId()->willReturn(1);
        $media->getName()->willReturn('media-1');
        $media->getVersion()->willReturn(1);
        $media->getSubVersion()->willReturn(0);

        $category->getMedias()->willReturn([$media->reveal()]);

        $this->categoryManager->findByIds(Argument::any())->shouldbeCalled();
        $this->categoryManager->getApiObjects(Argument::any(), $locale)->willReturn([$category->reveal()]);

        $property = $this->prophesize(PropertyInterface::class);

        $this->categorySerializer->serialize($category, Argument::type(SerializationContext::class))->willReturn([
            'id' => 1,
            'locale' => 'en',
            'key' => 'key-1',
            'name' => 'cat-1',
            'desc' => 'desc-1',
            'medias' => [
                [
                    'id' => 1,
                    'formatUri' => '/media/1/{format}/media-1.jpg?=v1-0',
                ],
            ],
        ]);

        $result = $this->categorySelectionResolver->resolve($data, $property->reveal(), $locale);

        $this->assertInstanceOf(ContentView::class, $result);

        $this->assertSame(
            [
                [
                    'id' => 1,
                    'locale' => 'en',
                    'key' => 'key-1',
                    'name' => 'cat-1',
                    'desc' => 'desc-1',
                    'medias' => [
                        [
                            'id' => 1,
                            'formatUri' => '/media/1/{format}/media-1.jpg?=v1-0',
                        ],
                    ],
                ],
            ],
            $result->getContent()
        );

        $this->assertSame(
            $view,
            $result->getView()
        );
    }

    /**
     * @return mixed[]
     */
    public function categoryResolverProvider(): array
    {
        return [
            [
                [
                    1,
                ],
                [
                    1,
                ],
            ],
            [
                [
                    [
                        'id' => 1,
                        'key' => 'key-1',
                        'name' => 'cat-1',
                        'desc' => 'desc-1',
                    ],
                ],
                [
                    1,
                ],
            ],
        ];
    }
}
