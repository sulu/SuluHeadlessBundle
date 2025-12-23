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
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TypedFormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderInterface;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderRegistry;
use Sulu\Bundle\HeadlessBundle\Content\ContentResolverInterface;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\ImageMapResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\MediaSerializerInterface;
use Sulu\Bundle\MediaBundle\Api\Media;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Symfony\Component\DependencyInjection\Container;

class ImageMapResolverTest extends TestCase
{
    use ProphecyTrait;

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

    private MetadataProviderRegistry $metadataProviderRegistry;

    /**
     * @var ImageMapResolver
     */
    private $imageMapResolver;

    private FieldMetadata $fieldMetadata;

    protected function setUp(): void
    {
        $this->mediaManager = $this->prophesize(MediaManagerInterface::class);
        $this->mediaSerializer = $this->prophesize(MediaSerializerInterface::class);
        $this->contentResolver = $this->prophesize(ContentResolverInterface::class);

        $formMetadataProvider = $this->prophesize(MetadataProviderInterface::class);
        $typedFormMetadata = new TypedFormMetadata();
        $formMetadataProvider->getMetadata('block', 'en', [])->willReturn($typedFormMetadata);

        $container = new Container();
        $container->set('form', $formMetadataProvider->reveal());
        $this->metadataProviderRegistry = new MetadataProviderRegistry($container);

        $this->fieldMetadata = new FieldMetadata('image_map');

        $this->imageMapResolver = $this->createResolver();
    }

    private function createResolver(): ImageMapResolver
    {
        return new ImageMapResolver(
            $this->mediaManager->reveal(),
            $this->mediaSerializer->reveal(),
            $this->contentResolver->reveal(),
            $this->metadataProviderRegistry,
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

        $basicType = new FormMetadata();
        $basicType->setKey('basic');

        $titleField = new FieldMetadata('title');
        $titleField->setType('text_line');
        $basicType->addItem($titleField);

        $descriptionField = new FieldMetadata('description');
        $descriptionField->setType('text_area');
        $basicType->addItem($descriptionField);

        $advancedType = new FormMetadata();
        $advancedType->setKey('advanced');

        $mediaField = new FieldMetadata('media');
        $mediaField->setType('single_media_selection');
        $advancedType->addItem($mediaField);

        $blockField = new FieldMetadata('block_1');
        $blockField->setType('block');
        $advancedType->addItem($blockField);

        $this->fieldMetadata->addType($basicType);
        $this->fieldMetadata->addType($advancedType);

        $contentViewTextLine = new ContentView('Test Point', []);
        $contentViewTextArea = new ContentView('Test Point description', []);

        $this->contentResolver->resolve('Test Point', $titleField, $locale, [])
            ->shouldBeCalled()
            ->willReturn($contentViewTextLine);

        $this->contentResolver->resolve('Test Point description', $descriptionField, $locale, [])
            ->shouldBeCalled()
            ->willReturn($contentViewTextArea);

        $contentViewMedia = new ContentView(['id' => 1, 'locale' => 'en'], ['id' => 1]);
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

        $this->contentResolver->resolve(['id' => 1], $mediaField, $locale, [])
            ->shouldBeCalled()
            ->willReturn($contentViewMedia);

        $this->contentResolver->resolve($blockValue, $blockField, $locale, [])
            ->shouldBeCalled()
            ->willReturn($contentViewBlock);

        $contentView = $this->imageMapResolver->resolve($data, $this->fieldMetadata, $locale);

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

        $result = $this->imageMapResolver->resolve(null, $this->fieldMetadata, $locale);

        self::assertSame([], $result->getContent());
        self::assertSame([], $result->getView());
    }

    public function testResolveDataIsEmptyArray(): void
    {
        $locale = 'en';

        $result = $this->imageMapResolver->resolve([], $this->fieldMetadata, $locale);

        self::assertSame([], $result->getContent());
        self::assertSame([], $result->getView());
    }

    public function testResolveWithInvalidHotspot(): void
    {
        $locale = 'en';
        $data = [
            'hotspots' => [
                'not-an-array',
                ['no-type' => 'missing type field'],
            ],
        ];

        $result = $this->imageMapResolver->resolve($data, $this->fieldMetadata, $locale);

        self::assertSame([], $result->getContent());
        self::assertSame([], $result->getView());
    }

    public function testResolveWithUnknownHotspotType(): void
    {
        $locale = 'en';
        $data = [
            'hotspots' => [
                [
                    'type' => 'unknown_type',
                    'title' => 'Test',
                ],
            ],
        ];

        $result = $this->imageMapResolver->resolve($data, $this->fieldMetadata, $locale);

        self::assertSame([
            'hotspots' => [
                [
                    'type' => 'unknown_type',
                    'title' => 'Test',
                ],
            ],
        ], $result->getContent());
        self::assertSame([
            'hotspots' => [
                [],
            ],
        ], $result->getView());
    }

    public function testResolveWithNonTypedFormMetadata(): void
    {
        $formMetadataProvider = $this->prophesize(MetadataProviderInterface::class);
        $formMetadataProvider->getMetadata('block', 'en', [])->willReturn(new FormMetadata());

        $container = new Container();
        $container->set('form', $formMetadataProvider->reveal());
        $metadataProviderRegistry = new MetadataProviderRegistry($container);

        $resolver = new ImageMapResolver(
            $this->mediaManager->reveal(),
            $this->mediaSerializer->reveal(),
            $this->contentResolver->reveal(),
            $metadataProviderRegistry,
        );

        $basicType = new FormMetadata();
        $basicType->setKey('basic');
        $titleField = new FieldMetadata('title');
        $titleField->setType('text_line');
        $basicType->addItem($titleField);
        $this->fieldMetadata->addType($basicType);

        $this->contentResolver->resolve('Test', $titleField, 'en', [])
            ->willReturn(new ContentView('Test', []));

        $data = [
            'hotspots' => [
                ['type' => 'basic', 'title' => 'Test'],
            ],
        ];

        $result = $resolver->resolve($data, $this->fieldMetadata, 'en');

        self::assertSame([
            'hotspots' => [
                ['type' => 'basic', 'title' => 'Test'],
            ],
        ], $result->getContent());
    }
}
