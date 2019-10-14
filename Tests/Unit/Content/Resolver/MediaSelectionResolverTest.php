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

namespace Sulu\Bundle\HeadlessBundle\Tests\Unit\Content\Resolver;

use JMS\Serializer\SerializerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\Resolver\MediaSelectionResolver;
use Sulu\Bundle\MediaBundle\Api\Media;
use Sulu\Bundle\MediaBundle\Media\FormatCache\FormatCacheInterface;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Sulu\Component\Content\Compat\PropertyInterface;

class MediaSelectionResolverTest extends TestCase
{
    /**
     * @var MediaManagerInterface|ObjectProphecy
     */
    private $mediaManager;

    /**
     * @var FormatCacheInterface|ObjectProphecy
     */
    private $formatCache;

    /**
     * @var SerializerInterface|ObjectProphecy
     */
    private $serializer;

    /**
     * @var MediaSelectionResolver
     */
    private $mediaResolver;

    protected function setUp(): void
    {
        $this->mediaManager = $this->prophesize(MediaManagerInterface::class);
        $this->formatCache = $this->prophesize(FormatCacheInterface::class);
        $this->serializer = $this->prophesize(SerializerInterface::class);

        $this->mediaResolver = new MediaSelectionResolver(
            $this->mediaManager->reveal(),
            $this->formatCache->reveal(),
            $this->serializer->reveal()
        );
    }

    public function testResolve(): void
    {
        $media1 = $this->prophesize(Media::class);
        $media1->getId()->willReturn(1);
        $media1->getName()->willReturn('media-1');
        $media1->getVersion()->willReturn(1);
        $media1->getSubVersion()->willReturn(0);

        $media2 = $this->prophesize(Media::class);
        $media2->getId()->willReturn(2);
        $media2->getName()->willReturn('media-2');
        $media2->getVersion()->willReturn(1);
        $media2->getSubVersion()->willReturn(0);

        $this->mediaManager->getByIds([1, 2], 'en')->willReturn([$media1->reveal(), $media2->reveal()]);

        $data1 = [
            'id' => 1,
            'formats' => [],
            'storageOptions' => [],
            'thumbnails' => [],
            'versions' => [],
            'downloadCounter' => [],
            '_hash' => [],
        ];
        $this->serializer->serialize($media1, 'json')->willReturn(json_encode($data1));

        $data2 = [
            'id' => 2,
            'formats' => [],
            'storageOptions' => [],
            'thumbnails' => [],
            'versions' => [],
            'downloadCounter' => [],
            '_hash' => [],
        ];
        $this->serializer->serialize($media2, 'json')->willReturn(json_encode($data2));

        $this->formatCache->getMediaUrl(1, 'media-1', '{format}', 1, 0)
            ->willReturn('/media/1/{format}/media-1.jpg?v=1-0');

        $this->formatCache->getMediaUrl(2, 'media-2', '{format}', 1, 0)
            ->willReturn('/media/2/{format}/media-2.jpg?v=1-0');

        $property = $this->prophesize(PropertyInterface::class);

        $result = $this->mediaResolver->resolve(['ids' => [1, 2]], $property->reveal(), 'en');

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame(
            [
                [
                    'id' => 1,
                    'formatUri' => '/media/1/{format}/media-1.jpg?v=1-0',
                ],
                [
                    'id' => 2,
                    'formatUri' => '/media/2/{format}/media-2.jpg?v=1-0',
                ],
            ],
            $result->getContent()
        );
        $this->assertSame(
            [
                'ids' => [1, 2],
            ],
            $result->getView()
        );
    }
}
