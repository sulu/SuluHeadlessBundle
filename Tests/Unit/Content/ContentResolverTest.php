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

namespace Sulu\Bundle\HeadlessBundle\Tests\Unit\Content;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\HeadlessBundle\Content\ContentResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentResolverInterface;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\ContentTypeResolverInterface;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Component\Content\Compat\PropertyInterface;

class ContentResolverTest extends TestCase
{
    /**
     * @var ContentTypeResolverInterface|ObjectProphecy
     */
    private $mediaSelectionResolver;

    /**
     * @var ContentResolverInterface
     */
    private $contentResolver;

    protected function setUp(): void
    {
        $this->mediaSelectionResolver = $this->prophesize(ContentTypeResolverInterface::class);

        $this->contentResolver = new ContentResolver(
            [
                'media_selection' => $this->mediaSelectionResolver->reveal(),
            ]
        );
    }

    public function testResolve(): void
    {
        $property = $this->prophesize(PropertyInterface::class);
        $property->getContentTypeName()->willReturn('media_selection');

        $contentView = $this->prophesize(ContentView::class);

        $this->mediaSelectionResolver->resolve('TEST', $property->reveal(), 'en', ['webspaceKey' => 'sulu_io'])
            ->willReturn($contentView->reveal());

        $result = $this->contentResolver->resolve('TEST', $property->reveal(), 'en', ['webspaceKey' => 'sulu_io']);
        $this->assertSame($contentView->reveal(), $result);
    }

    public function testResolveNoResolverFound(): void
    {
        $property = $this->prophesize(PropertyInterface::class);
        $property->getContentTypeName()->willReturn('text_line');

        $this->mediaSelectionResolver->resolve(Argument::cetera())->shouldNotBeCalled();

        $result = $this->contentResolver->resolve('TEST', $property->reveal(), 'en', ['webspaceKey' => 'sulu_io']);
        $this->assertSame('TEST', $result->getContent());
        $this->assertSame([], $result->getView());
    }
}
