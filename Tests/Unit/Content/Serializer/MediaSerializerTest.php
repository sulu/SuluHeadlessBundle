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
use Sulu\Bundle\HeadlessBundle\Content\Serializer\MediaSerializer;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\MediaSerializerInterface;
use Sulu\Bundle\MediaBundle\Api\Media;
use Sulu\Bundle\MediaBundle\Media\FormatCache\FormatCacheInterface;
use Sulu\Bundle\MediaBundle\Media\ImageConverter\ImageConverterInterface;
use Sulu\Component\Serializer\ArraySerializerInterface;

class MediaSerializerTest extends TestCase
{
    /**
     * @var ArraySerializerInterface|ObjectProphecy
     */
    private $arraySerializer;

    /**
     * @var ImageConverterInterface|ObjectProphecy
     */
    private $imageConverter;

    /**
     * @var FormatCacheInterface|ObjectProphecy
     */
    private $formatCache;

    /**
     * @var MediaSerializerInterface
     */
    private $mediaSerializer;

    protected function setUp(): void
    {
        $this->arraySerializer = $this->prophesize(ArraySerializerInterface::class);
        $this->imageConverter = $this->prophesize(ImageConverterInterface::class);
        $this->formatCache = $this->prophesize(FormatCacheInterface::class);

        $this->mediaSerializer = new MediaSerializer(
            $this->arraySerializer->reveal(),
            $this->imageConverter->reveal(),
            $this->formatCache->reveal()
        );
    }

    public function testSerialize(): void
    {
        $media1 = $this->prophesize(Media::class);
        $media1->getId()->willReturn(1);
        $media1->getName()->willReturn('media-1.png');
        $media1->getMimeType()->willReturn('image/png');
        $media1->getVersion()->willReturn(1);
        $media1->getSubVersion()->willReturn(0);

        $this->arraySerializer->serialize($media1, null)->willReturn([
            'id' => 1,
            'formats' => [],
            'storageOptions' => [],
            'thumbnails' => [],
            'versions' => [],
            'downloadCounter' => [],
            '_hash' => [],
        ]);

        $this->imageConverter->getSupportedOutputImageFormats('image/png')->willReturn(['jpg']);

        $this->formatCache->getMediaUrl(1, 'media-1.jpg', '{format}', 1, 0)
            ->willReturn('/media/1/{format}/media-1.jpg?v=1-0');

        $result = $this->mediaSerializer->serialize($media1->reveal());

        $this->assertSame([
            'id' => 1,
            'formatUri' => '/media/1/{format}/media-1.jpg?v=1-0',
        ], $result);
    }

    public function testSerializeWithContext(): void
    {
        $media1 = $this->prophesize(Media::class);
        $media1->getId()->willReturn(1);
        $media1->getName()->willReturn('media-1.png');
        $media1->getMimeType()->willReturn('image/png');
        $media1->getVersion()->willReturn(1);
        $media1->getSubVersion()->willReturn(0);

        $context = $this->prophesize(SerializationContext::class);

        $this->arraySerializer->serialize($media1, $context)->willReturn([
            'id' => 1,
            'formats' => [],
            'storageOptions' => [],
            'thumbnails' => [],
            'versions' => [],
            'downloadCounter' => [],
            '_hash' => [],
        ]);

        $this->imageConverter->getSupportedOutputImageFormats('image/png')->willReturn([]);

        $this->formatCache->getMediaUrl(1, 'media-1.png', '{format}', 1, 0)
            ->willReturn('/media/1/{format}/media-1.png?v=1-0');

        $result = $this->mediaSerializer->serialize($media1->reveal(), $context->reveal());

        $this->assertSame([
            'id' => 1,
            'formatUri' => '/media/1/{format}/media-1.png?v=1-0',
        ], $result);
    }
}
