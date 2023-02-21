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
use Sulu\Bundle\MediaBundle\Entity\File;
use Sulu\Bundle\MediaBundle\Entity\FileVersion;
use Sulu\Bundle\MediaBundle\Entity\MediaInterface;
use Sulu\Bundle\MediaBundle\Media\FormatCache\FormatCacheInterface;
use Sulu\Bundle\MediaBundle\Media\FormatCache\LocalFormatCache;
use Sulu\Bundle\MediaBundle\Media\ImageConverter\ImageConverterInterface;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Sulu\Bundle\WebsiteBundle\ReferenceStore\ReferenceStoreInterface;
use Sulu\Component\Serializer\ArraySerializerInterface;
use Symfony\Component\Filesystem\Filesystem;

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
     * @var FormatCacheInterface
     */
    private $formatCache;

    /**
     * @var ReferenceStoreInterface|ObjectProphecy
     */
    private $referenceStore;

    /**
     * @var MediaSerializerInterface
     */
    private $mediaSerializer;

    protected function setUp(): void
    {
        $this->mediaManager = $this->prophesize(MediaManagerInterface::class);
        $this->arraySerializer = $this->prophesize(ArraySerializerInterface::class);
        $this->imageConverter = $this->prophesize(ImageConverterInterface::class);
        $this->referenceStore = $this->prophesize(ReferenceStoreInterface::class);
        $this->formatCache = new LocalFormatCache(new Filesystem(), __DIR__ . '../../../Application/var/public/uploads/media', '/uploads/media/{slug}', 10);

        $this->mediaSerializer = new MediaSerializer(
            $this->mediaManager->reveal(),
            $this->arraySerializer->reveal(),
            $this->imageConverter->reveal(),
            $this->formatCache,
            $this->referenceStore->reveal()
        );
    }

    public function testSerialize(): void
    {
        $locale = 'en';
        $media = $this->prophesize(MediaInterface::class);

        // expected and unexpected object calls
        $apiMedia = $this->prophesize(Media::class);
        $apiMedia->getId()->willReturn(1)->shouldBeCalled();
        $apiMedia->getName()->willReturn('media-1.png')->shouldBeCalled();
        $apiMedia->getMimeType()->willReturn('image/png')->shouldBeCalled();
        $apiMedia->getVersion()->willReturn(1)->shouldBeCalled();
        $apiMedia->getSubVersion()->willReturn(0)->shouldBeCalled();

        $apiMediaArgument = Argument::that(function (Media $apiMedia) use ($media, $locale) {
            return $apiMedia->getEntity() === $media->reveal() && $locale === $apiMedia->getLocale();
        });

        // expected and unexpected service calls
        $this->mediaManager->addFormatsAndUrl($apiMediaArgument)
            ->willReturn($apiMedia->reveal())
            ->shouldBeCalled();

        $this->arraySerializer->serialize($apiMedia->reveal(), null)->willReturn([
            'id' => 1,
            'formats' => [],
            'storageOptions' => [],
            'thumbnails' => [],
            'versions' => [],
            'downloadCounter' => [],
            '_hash' => [],
        ])->shouldBeCalled();

        $this->imageConverter->getSupportedOutputImageFormats('image/png')
            ->willReturn(['png'])
            ->shouldBeCalled();

        $this->referenceStore->add(1)
            ->shouldBeCalled();

        // call test function
        $result = $this->mediaSerializer->serialize($media->reveal(), $locale);

        $this->assertSame([
            'id' => 1,
            'formatPreferredExtension' => 'png',
            'formatUri' => '/uploads/media/{format}/01/1-media-1.{extension}?v=1-0',
        ], $result);
    }

    public function testSerializeDocument(): void
    {
        $locale = 'en';
        $media = $this->prophesize(MediaInterface::class);

        // expected and unexpected object calls
        $apiMedia = $this->prophesize(Media::class);
        $apiMedia->getId()->willReturn(1)->shouldBeCalled();
        $apiMedia->getName()->willReturn('media-1.pdf')->shouldBeCalled();
        $apiMedia->getMimeType()->willReturn('application/pdf')->shouldBeCalled();

        $apiMediaArgument = Argument::that(function (Media $apiMedia) use ($media, $locale) {
            return $apiMedia->getEntity() === $media->reveal() && $locale === $apiMedia->getLocale();
        });

        // expected and unexpected service calls
        $this->mediaManager->addFormatsAndUrl($apiMediaArgument)
            ->willReturn($apiMedia->reveal())
            ->shouldBeCalled();

        $this->arraySerializer->serialize($apiMedia->reveal(), null)->willReturn([
            'id' => 1,
            'formats' => [],
            'storageOptions' => [],
            'thumbnails' => [],
            'versions' => [],
            'downloadCounter' => [],
            '_hash' => [],
        ])->shouldBeCalled();

        $this->imageConverter->getSupportedOutputImageFormats('application/pdf')
            ->willReturn([])
            ->shouldBeCalled();

        $this->referenceStore->add(1)
            ->shouldBeCalled();

        // call test function
        $result = $this->mediaSerializer->serialize($media->reveal(), $locale);

        $this->assertSame([
            'id' => 1,
        ], $result);
    }

    public function testSerializeWithPreviewImage(): void
    {
        $locale = 'en';
        $media = $this->prophesize(MediaInterface::class);

        // expected and unexpected object calls
        $apiMedia = $this->prophesize(Media::class);
        $apiMedia->getId()->willReturn(1)->shouldBeCalled();

        $previewMediaFileVersion = $this->prophesize(FileVersion::class);
        $previewMediaFileVersion->getName()->willReturn('preview-media.png')->shouldBeCalled();
        $previewMediaFileVersion->getMimeType()->willReturn('image/png')->shouldBeCalled();
        $previewMediaFileVersion->getVersion()->willReturn(1)->shouldBeCalled();
        $previewMediaFileVersion->getSubVersion()->willReturn(0)->shouldBeCalled();

        $previewMediaFile = $this->prophesize(File::class);
        $previewMediaFile->getVersion()->willReturn(1)->shouldBeCalled();
        $previewMediaFile->getFileVersion(1)->willReturn($previewMediaFileVersion->reveal())->shouldBeCalled();

        $previewMedia = $this->prophesize(MediaInterface::class);
        $previewMedia->getFiles()->willReturn([$previewMediaFile->reveal()])->shouldBeCalled();

        $previewMedia->getId()->willReturn(1)->shouldBeCalled();
        $media->getPreviewImage()->willReturn($previewMedia->reveal())->shouldBeCalled();

        $apiMediaArgument = Argument::that(function (Media $apiMedia) use ($media, $locale) {
            return $apiMedia->getEntity() === $media->reveal() && $locale === $apiMedia->getLocale();
        });

        // expected and unexpected service calls
        $this->mediaManager->addFormatsAndUrl($apiMediaArgument)
            ->willReturn($apiMedia->reveal())
            ->shouldBeCalled();

        $this->arraySerializer->serialize($apiMedia->reveal(), null)->willReturn([
            'id' => 1,
            'formats' => [],
            'storageOptions' => [],
            'thumbnails' => [],
            'versions' => [],
            'downloadCounter' => [],
            '_hash' => [],
        ])->shouldBeCalled();

        $this->imageConverter->getSupportedOutputImageFormats('image/png')
            ->willReturn(['png'])
            ->shouldBeCalled();

        $this->referenceStore->add(1)
            ->shouldBeCalled();

        // call test function
        $result = $this->mediaSerializer->serialize($media->reveal(), $locale);

        $this->assertSame([
            'id' => 1,
            'formatPreferredExtension' => 'png',
            'formatUri' => '/uploads/media/{format}/01/1-preview-media.{extension}?v=1-0',
        ], $result);
    }

    public function testSerializeWithContext(): void
    {
        $locale = 'en';
        $media = $this->prophesize(MediaInterface::class);

        // expected and unexpected object calls
        $apiMedia = $this->prophesize(Media::class);
        $apiMedia->getId()->willReturn(1)->shouldBeCalled();
        $apiMedia->getName()->willReturn('media-1.png')->shouldBeCalled();
        $apiMedia->getMimeType()->willReturn('image/png')->shouldBeCalled();
        $apiMedia->getVersion()->willReturn(1)->shouldBeCalled();
        $apiMedia->getSubVersion()->willReturn(0)->shouldBeCalled();

        $apiMediaArgument = Argument::that(function (Media $apiMedia) use ($media, $locale) {
            return $apiMedia->getEntity() === $media->reveal() && $locale === $apiMedia->getLocale();
        });

        // expected and unexpected service calls
        $this->mediaManager->addFormatsAndUrl($apiMediaArgument)
            ->willReturn($apiMedia->reveal())
            ->shouldBeCalled();

        $context = $this->prophesize(SerializationContext::class);

        $this->arraySerializer->serialize($apiMedia->reveal(), $context)->willReturn([
            'id' => 1,
            'formats' => [],
            'storageOptions' => [],
            'thumbnails' => [],
            'versions' => [],
            'downloadCounter' => [],
            '_hash' => [],
        ])->shouldBeCalled();

        $this->imageConverter->getSupportedOutputImageFormats('image/png')
            ->willReturn(['png'])
            ->shouldBeCalled();

        $this->referenceStore->add(1)
            ->shouldBeCalled();

        // call test function
        $result = $this->mediaSerializer->serialize($media->reveal(), $locale, $context->reveal());

        $this->assertSame([
            'id' => 1,
            'formatPreferredExtension' => 'png',
            'formatUri' => '/uploads/media/{format}/01/1-media-1.{extension}?v=1-0',
        ], $result);
    }
}
