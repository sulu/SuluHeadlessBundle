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
use Sulu\Bundle\CategoryBundle\Category\CategoryManagerInterface;
use Sulu\Bundle\CategoryBundle\Entity\CategoryInterface;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\CategorySerializer;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\CategorySerializerInterface;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\MediaSerializerInterface;
use Sulu\Bundle\MediaBundle\Api\Media;
use Sulu\Bundle\MediaBundle\Entity\MediaInterface;
use Sulu\Component\Serializer\ArraySerializerInterface;

class CategorySerializerTest extends TestCase
{
    /**
     * @var CategoryManagerInterface|ObjectProphecy
     */
    private $categoryManager;

    /**
     * @var ArraySerializerInterface|ObjectProphecy
     */
    private $arraySerializer;

    /**
     * @var MediaSerializerInterface|ObjectProphecy
     */
    private $mediaSerializer;

    /**
     * @var CategorySerializerInterface
     */
    private $categorySerializer;

    protected function setUp(): void
    {
        $this->categoryManager = $this->prophesize(CategoryManagerInterface::class);
        $this->arraySerializer = $this->prophesize(ArraySerializerInterface::class);
        $this->mediaSerializer = $this->prophesize(MediaSerializerInterface::class);

        $this->categorySerializer = new CategorySerializer(
            $this->categoryManager->reveal(),
            $this->arraySerializer->reveal(),
            $this->mediaSerializer->reveal()
        );
    }

    public function testSerialize(): void
    {
        $locale = 'en';

        $category = $this->prophesize(CategoryInterface::class);

        $media = $this->prophesize(MediaInterface::class);
        $apiMedia = $this->prophesize(Media::class);
        $apiMedia->getEntity()->willReturn($media->reveal());

        $apiCategory = $this->prophesize(Category::class);
        $apiCategory->getMedias()->willReturn([$apiMedia->reveal()]);

        $this->categoryManager->getApiObject($category->reveal(), $locale)->willReturn($apiCategory->reveal());

        $this->arraySerializer->serialize($apiCategory, null)->willReturn([
            'id' => 1,
            'locale' => 'en',
            'defaultLocale' => 'en',
            'key' => 'key-1',
            'name' => 'cat-1',
            'desc' => 'desc-1',
            'medias' => [1],
            'meta' => [],
        ]);

        $this->mediaSerializer->serialize($media, $locale)->willReturn([
            'id' => 1,
            'formatUri' => '/media/1/{format}/media-1.jpg?=v1-0',
        ]);

        $result = $this->categorySerializer->serialize($category->reveal(), $locale);

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
        $locale = 'en';
        $context = $this->prophesize(SerializationContext::class);

        $category = $this->prophesize(CategoryInterface::class);

        $media = $this->prophesize(MediaInterface::class);
        $apiMedia = $this->prophesize(Media::class);
        $apiMedia->getEntity()->willReturn($media->reveal());

        $apiCategory = $this->prophesize(Category::class);
        $apiCategory->getMedias()->willReturn([$apiMedia->reveal()]);

        $this->categoryManager->getApiObject($category->reveal(), $locale)->willReturn($apiCategory->reveal());

        $this->arraySerializer->serialize($apiCategory, $context)->willReturn([
            'id' => 1,
            'locale' => 'en',
            'defaultLocale' => 'en',
            'key' => 'key-1',
            'name' => 'cat-1',
            'desc' => 'desc-1',
            'medias' => [1],
            'meta' => [],
        ]);

        $this->mediaSerializer->serialize($media, $locale)->willReturn([
            'id' => 1,
            'formatUri' => '/media/1/{format}/media-1.jpg?=v1-0',
        ]);

        $result = $this->categorySerializer->serialize($category->reveal(), $locale, $context->reveal());

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
