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

namespace Sulu\Bundle\HeadlessBundle\Tests\Unit\Content\Serializer;

use JMS\Serializer\SerializationContext;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\CategoryBundle\Api\Category;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\CategorySerializer;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\MediaSerializer;
use Sulu\Bundle\MediaBundle\Api\Media;
use Sulu\Component\Serializer\ArraySerializerInterface;

class CategorySerializerTest extends TestCase
{
    /**
     * @var ArraySerializerInterface|ObjectProphecy
     */
    private $arraySerializer;

    /**
     * @var MediaSerializer|ObjectProphecy
     */
    private $mediaSerializer;

    /**
     * @var CategorySerializer
     */
    private $categorySerializer;

    protected function setUp(): void
    {
        $this->arraySerializer = $this->prophesize(ArraySerializerInterface::class);

        $this->mediaSerializer = $this->prophesize(MediaSerializer::class);

        $this->categorySerializer = new CategorySerializer(
            $this->arraySerializer->reveal(),
            $this->mediaSerializer->reveal()
        );
    }

    public function testSerialize(): void
    {
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

        $this->arraySerializer->serialize($category, null)->willReturn([
            'id' => 1,
            'locale' => 'en',
            'defaultLocale' => 'en',
            'key' => 'key-1',
            'name' => 'cat-1',
            'desc' => 'desc-1',
            'medias' => [1],
            'meta' => [],
        ]);

        $this->mediaSerializer->serialize($media)->willReturn([
            'id' => 1,
            'formatUri' => '/media/1/{format}/media-1.jpg?=v1-0',
        ]);

        $result = $this->categorySerializer->serialize($category->reveal());

        $this->assertSame([
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
        ], $result);
    }

    public function testSerializeWithContext(): void
    {
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

        $context = $this->prophesize(SerializationContext::class);

        $this->arraySerializer->serialize($category, $context)->willReturn([
            'id' => 1,
            'locale' => 'en',
            'defaultLocale' => 'en',
            'key' => 'key-1',
            'name' => 'cat-1',
            'desc' => 'desc-1',
            'medias' => [1],
            'meta' => [],
        ]);

        $this->mediaSerializer->serialize($media)->willReturn([
            'id' => 1,
            'formatUri' => '/media/1/{format}/media-1.jpg?=v1-0',
        ]);

        $result = $this->categorySerializer->serialize($category->reveal(), $context->reveal());

        $this->assertSame([
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
        ], $result);
    }
}
