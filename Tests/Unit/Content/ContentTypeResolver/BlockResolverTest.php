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
use Prophecy\Argument;
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

    /**
     * @var BlockVisitorInterface|ObjectProphecy
     */
    private $blockVisitor1;

    /**
     * @var BlockVisitorInterface|ObjectProphecy
     */
    private $blockVisitor2;

    /**
     * @var BlockResolver
     */
    private $blockResolver;

    protected function setUp(): void
    {
        $this->contentResolver = $this->prophesize(ContentResolverInterface::class);
        $this->blockVisitor1 = $this->prophesize(BlockVisitorInterface::class);
        $this->blockVisitor2 = $this->prophesize(BlockVisitorInterface::class);

        $this->blockVisitor1->visit(Argument::any())->will(function ($arguments) {return $arguments[0]; });
        $this->blockVisitor2->visit(Argument::any())->will(function ($arguments) {return $arguments[0]; });

        $this->blockResolver = new BlockResolver(
            $this->contentResolver->reveal(),
            new \ArrayIterator([$this->blockVisitor1->reveal(), $this->blockVisitor2->reveal()])
        );
    }

    public function testGetContentType(): void
    {
        self::assertSame('block', $this->blockResolver::getContentType());
    }

    public function testResolve(): void
    {
        $titleProperty = $this->prophesize(PropertyInterface::class);
        $titleProperty->getName()->willReturn('title');
        $titleProperty->getValue()->willReturn('test-123');

        $titleType = $this->prophesize(BlockPropertyType::class);
        $titleType->getName()->willReturn('title');
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
                'title' => 'test-123',
            ],
            [
                'type' => 'media',
                'media' => ['ids' => [1, 2, 3]],
            ],
        ];

        $blockProperty = $this->prophesize(BlockPropertyInterface::class);
        $blockProperty->getValue()->willReturn($data);
        $blockProperty->getLength()->willReturn(2);

        $blockProperty->getProperties(0)->willReturn($titleType->reveal());
        $this->blockVisitor1->visit($titleType->reveal())->willReturn($titleType->reveal());
        $this->blockVisitor2->visit($titleType->reveal())->willReturn($titleType->reveal());

        $blockProperty->getProperties(1)->willReturn($mediaType->reveal());
        $this->blockVisitor1->visit($mediaType->reveal())->willReturn($mediaType->reveal());
        $this->blockVisitor2->visit($mediaType->reveal())->willReturn($mediaType->reveal());

        $result = $this->blockResolver->resolve($data, $blockProperty->reveal(), 'en', ['webspaceKey' => 'sulu_io']);

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame(
            [
                [
                    'type' => 'title',
                    'title' => 'test-123',
                ],
                [
                    'type' => 'media',
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
        $titleProperty = $this->prophesize(PropertyInterface::class);
        $titleProperty->getName()->willReturn('title');
        $titleProperty->getValue()->willReturn('test-123');

        $titleType = $this->prophesize(BlockPropertyType::class);
        $titleType->getName()->willReturn('title');
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
                'title' => 'test-123',
            ],
            [
                'type' => 'media',
                'media' => ['ids' => [1, 2, 3]],
            ],
        ];

        $blockProperty = $this->prophesize(BlockPropertyInterface::class);
        $blockProperty->getValue()->willReturn($data);
        $blockProperty->getLength()->willReturn(2);

        $blockProperty->getProperties(0)->willReturn($titleType->reveal());
        $this->blockVisitor1->visit($titleType->reveal())->willReturn(null);
        $this->blockVisitor2->visit($titleType->reveal())->willReturn($titleType->reveal());

        $blockProperty->getProperties(1)->willReturn($mediaType->reveal());
        $this->blockVisitor1->visit($mediaType->reveal())->willReturn($mediaType->reveal());
        $this->blockVisitor2->visit($mediaType->reveal())->willReturn(null);

        $result = $this->blockResolver->resolve($data, $blockProperty->reveal(), 'en', ['webspaceKey' => 'sulu_io']);

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
}
