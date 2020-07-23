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
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\MediaSerializer;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\MediaSerializerInterface;
use Sulu\Bundle\MediaBundle\Api\Media;
use Sulu\Bundle\MediaBundle\Entity\MediaInterface;
use Sulu\Bundle\MediaBundle\Media\FormatCache\FormatCacheInterface;
use Sulu\Bundle\MediaBundle\Media\ImageConverter\ImageConverterInterface;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Sulu\Component\Serializer\ArraySerializerInterface;

class MediaSerializerTest extends TestCase
{
    /**
     * @var MediaManagerInterface|ObjectProphecy
     */
    private $mediaManager;

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
        $this->mediaManager = $this->prophesize(MediaManagerInterface::class);
        $this->arraySerializer = $this->prophesize(ArraySerializerInterface::class);
        $this->imageConverter = $this->prophesize(ImageConverterInterface::class);
        $this->formatCache = $this->prophesize(FormatCacheInterface::class);

        $this->mediaSerializer = new MediaSerializer(
            $this->mediaManager->reveal(),
            $this->arraySerializer->reveal(),
            $this->imageConverter->reveal(),
            $this->formatCache->reveal()
        );
    }

    public function testSerialize(): void
    {
        $locale = 'en';

        $media = $this->prophesize(MediaInterface::class);

        $apiMedia = $this->prophesize(Media::class);
        $apiMedia->getId()->willReturn(1);
        $apiMedia->getName()->willReturn('media-1.png');
        $apiMedia->getMimeType()->willReturn('image/png');
        $apiMedia->getVersion()->willReturn(1);
        $apiMedia->getSubVersion()->willReturn(0);

        $apiMediaArgument = Argument::that(function (Media $apiMedia) use ($media, $locale) {
            return $apiMedia->getEntity() === $media->reveal() && $locale === $apiMedia->getLocale();
        });
        $this->mediaManager->addFormatsAndUrl($apiMediaArgument)->willReturn($apiMedia->reveal());

        $this->arraySerializer->serialize($apiMedia->reveal(), null)->willReturn([
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

        $result = $this->mediaSerializer->serialize($media->reveal(), $locale);

        $this->assertSame([
            'id' => 1,
            'formatUri' => '/media/1/{format}/media-1.jpg?v=1-0',
        ], $result);
    }

    public function testSerializeWithContext(): void
    {
        $locale = 'en';

        $media = $this->prophesize(MediaInterface::class);

        $apiMedia = $this->prophesize(Media::class);
        $apiMedia->getId()->willReturn(1);
        $apiMedia->getName()->willReturn('media-1.png');
        $apiMedia->getMimeType()->willReturn('image/png');
        $apiMedia->getVersion()->willReturn(1);
        $apiMedia->getSubVersion()->willReturn(0);

        $apiMediaArgument = Argument::that(function (Media $apiMedia) use ($media, $locale) {
            return $apiMedia->getEntity() === $media->reveal() && $locale === $apiMedia->getLocale();
        });
        $this->mediaManager->addFormatsAndUrl($apiMediaArgument)->willReturn($apiMedia->reveal());

        $context = $this->prophesize(SerializationContext::class);

        $this->arraySerializer->serialize($apiMedia->reveal(), $context)->willReturn([
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

        $result = $this->mediaSerializer->serialize($media->reveal(), $locale, $context->reveal());

        $this->assertSame([
            'id' => 1,
            'formatUri' => '/media/1/{format}/media-1.png?v=1-0',
        ], $result);
    }
}
