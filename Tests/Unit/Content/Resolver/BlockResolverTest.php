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

use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\HeadlessBundle\Content\ContentResolverInterface;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\Resolver\BlockResolver;
use Sulu\Component\Content\Compat\Block\BlockPropertyInterface;
use Sulu\Component\Content\Compat\Block\BlockPropertyType;
use Sulu\Component\Content\Compat\PropertyInterface;

class BlockResolverTest extends TestCase
{
    /**
     * @var ContentResolverInterface|ObjectProphecy
     */
    private $contentResolver;

    /**
     * @var BlockResolver
     */
    private $blockResolver;

    protected function setUp(): void
    {
        $this->contentResolver = $this->prophesize(ContentResolverInterface::class);

        $this->blockResolver = new BlockResolver($this->contentResolver->reveal());
    }

    public function testResolve(): void
    {
        $titleProperty = $this->prophesize(PropertyInterface::class);
        $titleProperty->getName()->willReturn('title');
        $titleProperty->getValue()->willReturn('test-123');
        $mediaProperty = $this->prophesize(PropertyInterface::class);
        $mediaProperty->getName()->willReturn('media');
        $mediaProperty->getValue()->willReturn(['ids' => [1, 2, 3]]);

        $contentView1 = $this->prophesize(ContentView::class);
        $contentView1->getContent()->willReturn('test-123');
        $contentView1->getView()->willReturn([]);

        $this->contentResolver->resolve('test-123', $titleProperty->reveal(), 'en', ['webspaceKey' => 'sulu_io'])
            ->willReturn($contentView1->reveal());

        $contentView2 = $this->prophesize(ContentView::class);
        $contentView2->getContent()->willReturn(['media1', 'media2', 'media3']);
        $contentView2->getView()->willReturn(['ids' => [1, 2, 3]]);

        $this->contentResolver->resolve(
            ['ids' => [1, 2, 3]],
            $mediaProperty->reveal(),
            'en',
            ['webspaceKey' => 'sulu_io']
        )->willReturn($contentView2->reveal());

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

        $titleType = $this->prophesize(BlockPropertyType::class);
        $titleType->getName()->willReturn('title');
        $titleType->getChildProperties()->willReturn([$titleProperty->reveal()]);

        $mediaType = $this->prophesize(BlockPropertyType::class);
        $mediaType->getName()->willReturn('media');
        $mediaType->getChildProperties()->willReturn([$mediaProperty->reveal()]);

        $blockProperty->getProperties(0)->willReturn($titleType->reveal());
        $blockProperty->getProperties(1)->willReturn($mediaType->reveal());

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
}
