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
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\BlockResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Content\Application\PropertyResolver\BlockVisitor\BlockVisitorInterface;
use Symfony\Component\DependencyInjection\Container;

class BlockResolverTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var ContentResolverInterface|ObjectProphecy
     */
    private $contentResolver;

    private MetadataProviderRegistry $metadataProviderRegistry;

    private FieldMetadata $fieldMetadata;

    protected function setUp(): void
    {
        $this->contentResolver = $this->prophesize(ContentResolverInterface::class);

        $formMetadataProvider = $this->prophesize(MetadataProviderInterface::class);
        $typedFormMetadata = new TypedFormMetadata();
        $formMetadataProvider->getMetadata('block', 'en', [])->willReturn($typedFormMetadata);

        $container = new Container();
        $container->set('form', $formMetadataProvider->reveal());
        $this->metadataProviderRegistry = new MetadataProviderRegistry($container);

        $this->fieldMetadata = new FieldMetadata('block');
    }

    public function testGetContentType(): void
    {
        self::assertSame('block', BlockResolver::getContentType());
    }

    public function testResolve(): void
    {
        $titleFieldMetadata = new FieldMetadata('title');
        $titleFieldMetadata->setType('text_line');

        $titleTypeMetadata = new FormMetadata();
        $titleTypeMetadata->setKey('title');
        $titleTypeMetadata->addItem($titleFieldMetadata);

        $mediaFieldMetadata = new FieldMetadata('media');
        $mediaFieldMetadata->setType('media_selection');

        $mediaTypeMetadata = new FormMetadata();
        $mediaTypeMetadata->setKey('media');
        $mediaTypeMetadata->addItem($mediaFieldMetadata);

        $this->fieldMetadata->addType($titleTypeMetadata);
        $this->fieldMetadata->addType($mediaTypeMetadata);

        $this->contentResolver->resolve(
            'test-123',
            $titleFieldMetadata,
            'en',
            ['webspaceKey' => 'sulu_io']
        )->willReturn(new ContentView('test-123', []));

        $this->contentResolver->resolve(
            ['ids' => [1, 2, 3]],
            $mediaFieldMetadata,
            'en',
            ['webspaceKey' => 'sulu_io']
        )->willReturn(new ContentView(['media1', 'media2', 'media3'], ['ids' => [1, 2, 3]]));

        $data = [
            [
                'type' => 'title',
                'settings' => ['segments' => [], 'target_groups' => ['developer']],
                'title' => 'test-123',
            ],
            [
                'type' => 'media',
                'settings' => ['segments' => [], 'target_groups' => ['customer']],
                'media' => ['ids' => [1, 2, 3]],
            ],
        ];

        $blockResolver = $this->createBlockResolver();

        $result = $blockResolver->resolve($data, $this->fieldMetadata, 'en', ['webspaceKey' => 'sulu_io']);

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame(
            [
                [
                    'type' => 'title',
                    'settings' => ['segments' => [], 'target_groups' => ['developer']],
                    'title' => 'test-123',
                ],
                [
                    'type' => 'media',
                    'settings' => ['segments' => [], 'target_groups' => ['customer']],
                    'media' => ['media1', 'media2', 'media3'],
                ],
            ],
            $result->getContent()
        );
        $this->assertSame(
            [
                [
                    'title' => [],
                ],
                [
                    'media' => ['ids' => [1, 2, 3]],
                ],
            ],
            $result->getView()
        );
    }

    public function testResolveWithVisitors(): void
    {
        $titleFieldMetadata = new FieldMetadata('title');
        $titleFieldMetadata->setType('text_line');

        $titleTypeMetadata = new FormMetadata();
        $titleTypeMetadata->setKey('title');
        $titleTypeMetadata->addItem($titleFieldMetadata);

        $mediaFieldMetadata = new FieldMetadata('media');
        $mediaFieldMetadata->setType('media_selection');

        $mediaTypeMetadata = new FormMetadata();
        $mediaTypeMetadata->setKey('media');
        $mediaTypeMetadata->addItem($mediaFieldMetadata);

        $this->fieldMetadata->addType($titleTypeMetadata);
        $this->fieldMetadata->addType($mediaTypeMetadata);

        $this->contentResolver->resolve(
            'test-123',
            $titleFieldMetadata,
            'en',
            ['webspaceKey' => 'sulu_io']
        )->willReturn(new ContentView('test-123', []));

        $this->contentResolver->resolve(
            ['ids' => [1, 2, 3]],
            $mediaFieldMetadata,
            'en',
            ['webspaceKey' => 'sulu_io']
        )->willReturn(new ContentView(['media1', 'media2', 'media3'], ['ids' => [1, 2, 3]]));

        $data = [
            [
                'type' => 'title',
                'settings' => [],
                'title' => 'test-123',
            ],
            [
                'type' => 'media',
                'settings' => ['target_groups' => ['customer']],
                'media' => ['ids' => [1, 2, 3]],
            ],
        ];

        $blockVisitor1 = $this->prophesize(BlockVisitorInterface::class);
        $blockVisitor2 = $this->prophesize(BlockVisitorInterface::class);

        $blockVisitor1->visit($data[0])->willReturn($data[0]);
        $blockVisitor2->visit($data[0])->willReturn($data[0]);

        $blockVisitor1->visit($data[1])->willReturn($data[1]);
        $blockVisitor2->visit($data[1])->willReturn($data[1]);

        $blockResolver = $this->createBlockResolver([$blockVisitor1->reveal(), $blockVisitor2->reveal()]);

        $result = $blockResolver->resolve($data, $this->fieldMetadata, 'en', ['webspaceKey' => 'sulu_io']);

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame(
            [
                [
                    'type' => 'title',
                    'settings' => [],
                    'title' => 'test-123',
                ],
                [
                    'type' => 'media',
                    'settings' => ['target_groups' => ['customer']],
                    'media' => ['media1', 'media2', 'media3'],
                ],
            ],
            $result->getContent()
        );
        $this->assertSame(
            [
                [
                    'title' => [],
                ],
                [
                    'media' => ['ids' => [1, 2, 3]],
                ],
            ],
            $result->getView()
        );
    }

    public function testResolveWithSkips(): void
    {
        $titleFieldMetadata = new FieldMetadata('title');
        $titleFieldMetadata->setType('text_line');

        $titleTypeMetadata = new FormMetadata();
        $titleTypeMetadata->setKey('title');
        $titleTypeMetadata->addItem($titleFieldMetadata);

        $mediaFieldMetadata = new FieldMetadata('media');
        $mediaFieldMetadata->setType('media_selection');

        $mediaTypeMetadata = new FormMetadata();
        $mediaTypeMetadata->setKey('media');
        $mediaTypeMetadata->addItem($mediaFieldMetadata);

        $this->fieldMetadata->addType($titleTypeMetadata);
        $this->fieldMetadata->addType($mediaTypeMetadata);

        $data = [
            [
                'type' => 'title',
                'settings' => ['hidden' => true],
                'title' => 'test-123',
            ],
            [
                'type' => 'media',
                'settings' => ['hidden' => true],
                'media' => ['ids' => [1, 2, 3]],
            ],
        ];

        $blockVisitor1 = $this->prophesize(BlockVisitorInterface::class);
        $blockVisitor2 = $this->prophesize(BlockVisitorInterface::class);

        $blockVisitor1->visit($data[0])->willReturn(null);
        $blockVisitor2->visit($data[0])->willReturn($data[0]);

        $blockVisitor1->visit($data[1])->willReturn($data[1]);
        $blockVisitor2->visit($data[1])->willReturn(null);

        $blockResolver = $this->createBlockResolver([$blockVisitor1->reveal(), $blockVisitor2->reveal()]);
        $result = $blockResolver->resolve($data, $this->fieldMetadata, 'en', ['webspaceKey' => 'sulu_io']);

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame(
            [],
            $result->getContent()
        );
        $this->assertSame(
            [],
            $result->getView()
        );
    }

    public function testResolveWithInvalidBlockItem(): void
    {
        $data = [
            'not-an-array',
            ['no-type' => 'missing type field'],
        ];

        $blockResolver = $this->createBlockResolver();
        $result = $blockResolver->resolve($data, $this->fieldMetadata, 'en', []);

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame([], $result->getContent());
        $this->assertSame([], $result->getView());
    }

    public function testResolveWithUnknownBlockType(): void
    {
        $data = [
            [
                'type' => 'unknown_type',
                'title' => 'Test',
            ],
        ];

        $blockResolver = $this->createBlockResolver();
        $result = $blockResolver->resolve($data, $this->fieldMetadata, 'en', []);

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame([], $result->getContent());
        $this->assertSame([], $result->getView());
    }

    public function testResolveWithNonTypedFormMetadata(): void
    {
        $formMetadataProvider = $this->prophesize(MetadataProviderInterface::class);
        $formMetadataProvider->getMetadata('block', 'en', [])->willReturn(new FormMetadata());

        $container = new Container();
        $container->set('form', $formMetadataProvider->reveal());
        $metadataProviderRegistry = new MetadataProviderRegistry($container);

        $titleFieldMetadata = new FieldMetadata('title');
        $titleFieldMetadata->setType('text_line');

        $titleTypeMetadata = new FormMetadata();
        $titleTypeMetadata->setKey('title');
        $titleTypeMetadata->addItem($titleFieldMetadata);

        $this->fieldMetadata->addType($titleTypeMetadata);

        $this->contentResolver->resolve('Test', $titleFieldMetadata, 'en', [])
            ->willReturn(new ContentView('Test', []));

        $resolver = new BlockResolver(
            $this->contentResolver->reveal(),
            $metadataProviderRegistry,
        );

        $data = [
            ['type' => 'title', 'title' => 'Test'],
        ];

        $result = $resolver->resolve($data, $this->fieldMetadata, 'en', []);

        $this->assertInstanceOf(ContentView::class, $result);
        $content = $result->getContent();
        $this->assertIsArray($content);
        $this->assertCount(1, $content);
    }

    /**
     * @param BlockVisitorInterface[] $blockVisitors
     */
    private function createBlockResolver(array $blockVisitors = []): BlockResolver
    {
        return new BlockResolver(
            $this->contentResolver->reveal(),
            $this->metadataProviderRegistry,
            new \ArrayIterator($blockVisitors)
        );
    }
}
