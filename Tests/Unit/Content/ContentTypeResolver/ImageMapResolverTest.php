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
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\HeadlessBundle\Content\ContentResolverInterface;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\ImageMapResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\MediaSerializerInterface;
use Sulu\Bundle\MediaBundle\Api\Media;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Sulu\Component\Content\Compat\Block\BlockProperty;
use Sulu\Component\Content\Compat\Property;
use Sulu\Component\Content\Compat\PropertyInterface;
use Sulu\Component\Content\Compat\PropertyType;

class ImageMapResolverTest extends TestCase
{
    /**
     * @var MediaManagerInterface|ObjectProphecy
     */
    private $mediaManager;

    /**
     * @var MediaSerializerInterface|ObjectProphecy
     */
    private $mediaSerializer;

    /**
     * @var ContentResolverInterface|ObjectProphecy
     */
    private $contentResolver;

    /**
     * @var ImageMapResolver
     */
    private $imageMapResolver;

    protected function setUp(): void
    {
        $this->mediaManager = $this->prophesize(MediaManagerInterface::class);
        $this->mediaSerializer = $this->prophesize(MediaSerializerInterface::class);
        $this->contentResolver = $this->prophesize(ContentResolverInterface::class);

        $this->imageMapResolver = new ImageMapResolver(
            $this->mediaManager->reveal(),
            $this->mediaSerializer->reveal(),
            $this->contentResolver->reveal()
        );
    }

    public function testGetContentType(): void
    {
        self::assertSame('image_map', $this->imageMapResolver::getContentType());
    }

    public function testResolve(): void
    {
        $locale = 'en';
        $data = [
            'imageId' => 1,
            'hotspots' => [
                [
                    'type' => 'basic',
                    'hotspot' => [
                        'type' => 'point',
                        'left' => 1,
                        'top' => 1,
                        'radius' => 0,
                    ],
                    'title' => 'Test Point',
                    'description' => 'Test Point description',
                ],
                [
                    'type' => 'advanced',
                    'hotspot' => [
                        'type' => 'rectangle',
                        'width' => 1,
                        'height' => 2,
                        'left' => 1,
                        'top' => 1,
                    ],
                    'media' => [
                        'id' => 1,
                    ],
                    'block_1' => [
                        [
                            'type' => 'text-with-image',
                            'image' => [
                                'displayOption' => null,
                                'id' => 1,
                            ],
                            'title' => 'Example title',
                        ],
                    ],
                ],
            ],
        ];

        /** @var Media|ObjectProphecy $media */
        $media = $this->prophesize(Media::class);
        /** @var \Sulu\Bundle\MediaBundle\Entity\Media|ObjectProphecy $mediaEntity */
        $mediaEntity = $this->prophesize(\Sulu\Bundle\MediaBundle\Entity\Media::class);
        $media->getEntity()
            ->shouldBeCalled()
            ->willReturn($mediaEntity->reveal());

        $this->mediaManager->getById(1, $locale)
            ->shouldBeCalled()
            ->willReturn($media->reveal());
        $this->mediaSerializer->serialize($mediaEntity, $locale)
            ->shouldBeCalled()
            ->willReturn([
                'id' => 1,
                'locale' => 'en',
            ]);

        /** @var PropertyInterface|ObjectProphecy $property */
        $property = $this->prophesize(PropertyInterface::class);

        // Basic hotspot
        $propertyTypeBasic = $this->prophesize(PropertyType::class);
        $property->initProperties(0, 'basic')
            ->shouldBeCalled()
            ->willReturn($propertyTypeBasic->reveal());

        $propertyTextLine = $this->prophesize(Property::class);
        $propertyTextLine->getName()
            ->shouldBeCalled()
            ->willReturn('title');
        $propertyTextLine->setValue('Test Point')
            ->shouldBeCalled();
        $propertyTextLine->getValue()
            ->shouldBeCalled()
            ->willReturn('Test Point');

        $propertyTextArea = $this->prophesize(Property::class);
        $propertyTextArea->getName()
            ->shouldBeCalled()
            ->willReturn('description');
        $propertyTextArea->setValue('Test Point description')
            ->shouldBeCalled();
        $propertyTextArea->getValue()
            ->shouldBeCalled()
            ->willReturn('Test Point description');

        $propertyTypeBasic->getChildProperties()
            ->shouldBeCalled()
            ->willReturn([$propertyTextLine->reveal(), $propertyTextArea->reveal()]);

        $contentViewTextLine = new ContentView('Test Point', []);
        $contentViewTextArea = new ContentView('Test Point description', []);

        $this->contentResolver->resolve('Test Point', $propertyTextLine, $locale, [])
            ->shouldBeCalled()
            ->willReturn($contentViewTextLine);

        $this->contentResolver->resolve('Test Point description', $propertyTextArea, $locale, [])
            ->shouldBeCalled()
            ->willReturn($contentViewTextArea);

        // Advanced hotspot
        $propertyTypeAdvanced = $this->prophesize(PropertyType::class);
        $property->initProperties(1, 'advanced')
            ->shouldBeCalled()
            ->willReturn($propertyTypeAdvanced->reveal());

        $propertyMediaSelection = $this->prophesize(Property::class);
        $propertyMediaSelection->getName()
            ->shouldBeCalled()
            ->willReturn('media');
        $propertyMediaSelection->setValue(['id' => 1])
            ->shouldBeCalled();
        $propertyMediaSelection->getValue()
            ->shouldBeCalled()
            ->willReturn(['id' => 1]);

        $propertyBlock = $this->prophesize(BlockProperty::class);
        $propertyBlock->getName()
            ->shouldBeCalled()
            ->willReturn('block_1');
        $blockValue = [
            [
                'type' => 'text-with-image',
                'image' => [
                    'id' => 1,
                    'displayOption' => null,
                ],
                'title' => 'Example title',
            ],
        ];
        $propertyBlock->setValue($blockValue)
            ->shouldBeCalled();
        $propertyBlock->getValue()
            ->shouldBeCalled()
            ->willReturn($blockValue);

        $propertyTypeAdvanced->getChildProperties()
            ->shouldBeCalled()
            ->willReturn([$propertyMediaSelection, $propertyBlock]);

        $contentViewMedia = new ContentView(['id' => 1, 'locale' => 'en'], ['id' => 1]);
        $contentViewBlock = new ContentView(
            [
                [
                    'type' => 'text-with-image',
                    'image' => [
                        'id' => 1,
                        'locale' => 'en',
                    ],
                    'title' => 'Example title',
                ],
            ],
            [
                [
                    'image' => [
                        'id' => 1,
                        'displayOption' => null,
                    ],
                    'title' => [],
                ],
            ]
        );

        $this->contentResolver->resolve(['id' => 1], $propertyMediaSelection, $locale, [])
            ->shouldBeCalled()
            ->willReturn($contentViewMedia);

        $this->contentResolver->resolve($blockValue, $propertyBlock, $locale, [])
            ->shouldBeCalled()
            ->willReturn($contentViewBlock);

        $contentView = $this->imageMapResolver->resolve($data, $property->reveal(), $locale);

        self::assertSame([
            'image' => [
                'id' => 1,
                'locale' => 'en',
            ],
            'hotspots' => [
                [
                    'type' => 'basic',
                    'hotspot' => [
                        'type' => 'point',
                        'left' => 1,
                        'top' => 1,
                        'radius' => 0,
                    ],
                    'title' => 'Test Point',
                    'description' => 'Test Point description',
                ],
                [
                    'type' => 'advanced',
                    'hotspot' => [
                        'type' => 'rectangle',
                        'width' => 1,
                        'height' => 2,
                        'left' => 1,
                        'top' => 1,
                    ],
                    'media' => [
                        'id' => 1,
                        'locale' => 'en',
                    ],
                    'block_1' => [
                        [
                            'type' => 'text-with-image',
                            'image' => [
                                'id' => 1,
                                'locale' => 'en',
                            ],
                            'title' => 'Example title',
                        ],
                    ],
                ],
            ],
        ], $contentView->getContent());

        self::assertSame([
            'image' => [
                'id' => 1,
            ],
            'hotspots' => [
                [
                    'title' => [],
                    'description' => [],
                ],
                [
                    'media' => ['id' => 1],
                    'block_1' => [
                        [
                            'image' => [
                                'id' => 1,
                                'displayOption' => null,
                            ],
                            'title' => [],
                        ],
                    ],
                ],
            ],
        ], $contentView->getView());
    }

    public function testResolveDataIsNull(): void
    {
        $locale = 'en';
        $property = $this->prophesize(Property::class);

        $result = $this->imageMapResolver->resolve(null, $property->reveal(), $locale);

        self::assertSame([], $result->getContent());
        self::assertSame([], $result->getView());
    }

    public function testResolveDataIsEmptyArray(): void
    {
        $locale = 'en';
        $property = $this->prophesize(Property::class);

        $result = $this->imageMapResolver->resolve([], $property->reveal(), $locale);

        self::assertSame([], $result->getContent());
        self::assertSame([], $result->getView());
    }
}
