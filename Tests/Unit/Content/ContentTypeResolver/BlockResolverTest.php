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
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\BlockResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Component\Content\Compat\Block\BlockPropertyInterface;
use Sulu\Component\Content\Compat\Block\BlockPropertyType;
use Sulu\Component\Content\Compat\PropertyInterface;
use Sulu\Component\Content\Types\Block\BlockVisitorInterface;

class BlockResolverTest extends TestCase
{
    /**
     * @var ContentResolverInterface|ObjectProphecy
     */
    private $contentResolver;

    protected function setUp(): void
    {
        $this->contentResolver = $this->prophesize(ContentResolverInterface::class);
    }

    public function testGetContentType(): void
    {
        self::assertSame('block', BlockResolver::getContentType());
    }

    public function testResolve(): void
    {
        $titleProperty = $this->prophesize(PropertyInterface::class);
        $titleProperty->getName()->willReturn('title');
        $titleProperty->getValue()->willReturn('test-123');

        $titleType = $this->prophesize(BlockPropertyType::class);
        $titleType->getName()->willReturn('title');
        $titleType->getSettings()->willReturn(['segments' => [], 'target_groups' => ['developer']]);
        $titleType->getChildProperties()->willReturn([$titleProperty->reveal()]);

        $titleContentView = $this->prophesize(ContentView::class);
        $titleContentView->getContent()->willReturn('test-123');
        $titleContentView->getView()->willReturn([]);

        $this->contentResolver->resolve(
            'test-123',
            $titleProperty->reveal(),
            'en',
            ['webspaceKey' => 'sulu_io']
        )->willReturn($titleContentView->reveal());

        $mediaProperty = $this->prophesize(PropertyInterface::class);
        $mediaProperty->getName()->willReturn('media');
        $mediaProperty->getValue()->willReturn(['ids' => [1, 2, 3]]);

        $mediaType = $this->prophesize(BlockPropertyType::class);
        $mediaType->getName()->willReturn('media');
        $mediaType->getSettings()->willReturn(['segments' => [], 'target_groups' => ['customer']]);
        $mediaType->getChildProperties()->willReturn([$mediaProperty->reveal()]);

        $mediaContentView = $this->prophesize(ContentView::class);
        $mediaContentView->getContent()->willReturn(['media1', 'media2', 'media3']);
        $mediaContentView->getView()->willReturn(['ids' => [1, 2, 3]]);

        $this->contentResolver->resolve(
            ['ids' => [1, 2, 3]],
            $mediaProperty->reveal(),
            'en',
            ['webspaceKey' => 'sulu_io']
        )->willReturn($mediaContentView->reveal());

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

        $blockProperty = $this->prophesize(BlockPropertyInterface::class);
        $blockProperty->getValue()->willReturn($data);
        $blockProperty->getLength()->willReturn(2);

        $blockProperty->getProperties(0)->willReturn($titleType->reveal());

        $blockProperty->getProperties(1)->willReturn($mediaType->reveal());

        $blockResolver = $this->createBlockResolver();

        $result = $blockResolver->resolve($data, $blockProperty->reveal(), 'en', ['webspaceKey' => 'sulu_io']);

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
        if (!class_exists(BlockVisitorInterface::class)) {
            $this->markTestSkipped('Requires a newer sulu version with block visitors.');
        }

        $titleProperty = $this->prophesize(PropertyInterface::class);
        $titleProperty->getName()->willReturn('title');
        $titleProperty->getValue()->willReturn('test-123');

        $titleType = $this->prophesize(BlockPropertyType::class);
        $titleType->getName()->willReturn('title');
        $titleType->getSettings()->willReturn([]);
        $titleType->getChildProperties()->willReturn([$titleProperty->reveal()]);

        $titleContentView = $this->prophesize(ContentView::class);
        $titleContentView->getContent()->willReturn('test-123');
        $titleContentView->getView()->willReturn([]);

        $this->contentResolver->resolve(
            'test-123',
            $titleProperty->reveal(),
            'en',
            ['webspaceKey' => 'sulu_io']
        )->willReturn($titleContentView->reveal());

        $mediaProperty = $this->prophesize(PropertyInterface::class);
        $mediaProperty->getName()->willReturn('media');
        $mediaProperty->getValue()->willReturn(['ids' => [1, 2, 3]]);

        $mediaType = $this->prophesize(BlockPropertyType::class);
        $mediaType->getName()->willReturn('media');
        $mediaType->getSettings()->willReturn(['target_groups' => ['customer']]);
        $mediaType->getChildProperties()->willReturn([$mediaProperty->reveal()]);

        $mediaContentView = $this->prophesize(ContentView::class);
        $mediaContentView->getContent()->willReturn(['media1', 'media2', 'media3']);
        $mediaContentView->getView()->willReturn(['ids' => [1, 2, 3]]);

        $this->contentResolver->resolve(
            ['ids' => [1, 2, 3]],
            $mediaProperty->reveal(),
            'en',
            ['webspaceKey' => 'sulu_io']
        )->willReturn($mediaContentView->reveal());

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

        $blockProperty = $this->prophesize(BlockPropertyInterface::class);
        $blockProperty->getValue()->willReturn($data);
        $blockProperty->getLength()->willReturn(2);

        $blockProperty->getProperties(0)->willReturn($titleType->reveal());
        $blockVisitor1->visit($titleType->reveal())->willReturn($titleType->reveal());
        $blockVisitor2->visit($titleType->reveal())->willReturn($titleType->reveal());

        $blockProperty->getProperties(1)->willReturn($mediaType->reveal());
        $blockVisitor1->visit($mediaType->reveal())->willReturn($mediaType->reveal());
        $blockVisitor2->visit($mediaType->reveal())->willReturn($mediaType->reveal());

        $blockResolver = $this->createBlockResolver([$blockVisitor1->reveal(), $blockVisitor2->reveal()]);

        $result = $blockResolver->resolve($data, $blockProperty->reveal(), 'en', ['webspaceKey' => 'sulu_io']);

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
        if (!class_exists(BlockVisitorInterface::class)) {
            $this->markTestSkipped('Requires a newer sulu version with block visitors.');
        }

        $titleProperty = $this->prophesize(PropertyInterface::class);
        $titleProperty->getName()->willReturn('title');
        $titleProperty->getValue()->willReturn('test-123');

        $titleType = $this->prophesize(BlockPropertyType::class);
        $titleType->getName()->willReturn('title');
        $titleType->getSettings()->willReturn(['hidden' => true]);
        $titleType->getChildProperties()->willReturn([$titleProperty->reveal()]);

        $titleContentView = $this->prophesize(ContentView::class);
        $titleContentView->getContent()->willReturn('test-123');
        $titleContentView->getView()->willReturn([]);

        $this->contentResolver->resolve(
            'test-123',
            $titleProperty->reveal(),
            'en',
            ['webspaceKey' => 'sulu_io']
        )->willReturn($titleContentView->reveal());

        $mediaProperty = $this->prophesize(PropertyInterface::class);
        $mediaProperty->getName()->willReturn('media');
        $mediaProperty->getValue()->willReturn(['ids' => [1, 2, 3]]);

        $mediaType = $this->prophesize(BlockPropertyType::class);
        $mediaType->getName()->willReturn('media');
        $mediaType->getSettings()->willReturn(['hidden' => true]);
        $mediaType->getChildProperties()->willReturn([$mediaProperty->reveal()]);

        $mediaContentView = $this->prophesize(ContentView::class);
        $mediaContentView->getContent()->willReturn(['media1', 'media2', 'media3']);
        $mediaContentView->getView()->willReturn(['ids' => [1, 2, 3]]);

        $this->contentResolver->resolve(
            ['ids' => [1, 2, 3]],
            $mediaProperty->reveal(),
            'en',
            ['webspaceKey' => 'sulu_io']
        )->willReturn($mediaContentView->reveal());

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

        $blockProperty = $this->prophesize(BlockPropertyInterface::class);
        $blockProperty->getValue()->willReturn($data);
        $blockProperty->getLength()->willReturn(2);

        $blockProperty->getProperties(0)->willReturn($titleType->reveal());
        $blockVisitor1->visit($titleType->reveal())->willReturn(null);
        $blockVisitor2->visit($titleType->reveal())->willReturn($titleType->reveal());

        $blockProperty->getProperties(1)->willReturn($mediaType->reveal());
        $blockVisitor1->visit($mediaType->reveal())->willReturn($mediaType->reveal());
        $blockVisitor2->visit($mediaType->reveal())->willReturn(null);

        $blockResolver = $this->createBlockResolver([$blockVisitor1->reveal(), $blockVisitor2->reveal()]);
        $result = $blockResolver->resolve($data, $blockProperty->reveal(), 'en', ['webspaceKey' => 'sulu_io']);

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

    /**
     * @param BlockVisitorInterface[] $blockVisitors
     */
    private function createBlockResolver(array $blockVisitors = []): BlockResolver
    {
        return new BlockResolver(
            $this->contentResolver->reveal(),
            new \ArrayIterator($blockVisitors)
        );
    }
}
