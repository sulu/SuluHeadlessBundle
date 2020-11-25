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

use JMS\Serializer\SerializationContext;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\CategoryBundle\Category\CategoryManagerInterface;
use Sulu\Bundle\CategoryBundle\Entity\CategoryInterface;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\CategorySelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\CategorySerializerInterface;
use Sulu\Component\Content\Compat\PropertyInterface;

class CategorySelectionResolverTest extends TestCase
{
    /**
     * @var CategoryManagerInterface|ObjectProphecy
     */
    private $categoryManager;

    /**
     * @var CategorySerializerInterface|ObjectProphecy
     */
    private $categorySerializer;

    /**
     * @var CategorySelectionResolver
     */
    private $categorySelectionResolver;

    protected function setUp(): void
    {
        $this->categoryManager = $this->prophesize(CategoryManagerInterface::class);
        $this->categorySerializer = $this->prophesize(CategorySerializerInterface::class);

        $this->categorySelectionResolver = new CategorySelectionResolver(
            $this->categoryManager->reveal(),
            $this->categorySerializer->reveal()
        );
    }

    public function testGetContentType(): void
    {
        self::assertSame('category_selection', $this->categorySelectionResolver::getContentType());
    }

    public function testResolve(): void
    {
        $locale = 'en';

        $category = $this->prophesize(CategoryInterface::class);

        $this->categoryManager->findByIds([1])->shouldBeCalled()->willReturn([$category->reveal()]);

        $this->categorySerializer->serialize(
            $category->reveal(),
            $locale,
            Argument::type(SerializationContext::class)
        )->willReturn([
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

        $property = $this->prophesize(PropertyInterface::class);

        $result = $this->categorySelectionResolver->resolve([1], $property->reveal(), $locale);

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
            ['ids' => [1]],
            $result->getView()
        );
    }

    public function testResolveDataIsNull(): void
    {
        $locale = 'en';
        $property = $this->prophesize(PropertyInterface::class);

        $result = $this->categorySelectionResolver->resolve(null, $property->reveal(), $locale);

        $this->assertSame([], $result->getContent());

        $this->assertSame(['ids' => []], $result->getView());
    }

    public function testResolveDataIsEmptyArray(): void
    {
        $locale = 'en';
        $property = $this->prophesize(PropertyInterface::class);

        $result = $this->categorySelectionResolver->resolve([], $property->reveal(), $locale);

        $this->assertSame([], $result->getContent());

        $this->assertSame(['ids' => []], $result->getView());
    }
}
