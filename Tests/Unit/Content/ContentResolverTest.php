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
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\HeadlessBundle\Content\ContentResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentResolverInterface;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\ContentTypeResolverInterface;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;

class ContentResolverTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var ContentTypeResolverInterface|ObjectProphecy
     */
    private $mediaSelectionResolver;

    /**
     * @var ContentResolverInterface
     */
    private $contentResolver;

    private FieldMetadata $fieldMetadata;

    protected function setUp(): void
    {
        $this->mediaSelectionResolver = $this->prophesize(ContentTypeResolverInterface::class);

        $this->contentResolver = new ContentResolver(
            new \ArrayIterator(['media_selection' => $this->mediaSelectionResolver->reveal()])
        );

        $this->fieldMetadata = new FieldMetadata('media');
        $this->fieldMetadata->setType('media_selection');
    }

    public function testResolve(): void
    {
        $contentView = $this->prophesize(ContentView::class);

        $this->mediaSelectionResolver->resolve('TEST', $this->fieldMetadata, 'en', ['webspaceKey' => 'sulu_io'])
            ->willReturn($contentView->reveal());

        $result = $this->contentResolver->resolve('TEST', $this->fieldMetadata, 'en', ['webspaceKey' => 'sulu_io']);
        $this->assertSame($contentView->reveal(), $result);
    }

    public function testResolveNoResolverFound(): void
    {
        $fieldMetadata = new FieldMetadata('text');
        $fieldMetadata->setType('text_line');

        $this->mediaSelectionResolver->resolve(Argument::cetera())->shouldNotBeCalled();

        $result = $this->contentResolver->resolve('TEST', $fieldMetadata, 'en', ['webspaceKey' => 'sulu_io']);
        $this->assertSame('TEST', $result->getContent());
        $this->assertSame([], $result->getView());
    }
}
