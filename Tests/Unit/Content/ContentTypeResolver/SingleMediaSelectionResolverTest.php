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
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\MediaSelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\SingleMediaSelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Component\Content\Compat\PropertyInterface;

class SingleMediaSelectionResolverTest extends TestCase
{
    /**
     * @var SingleMediaSelectionResolver
     */
    private $singleMediaSelectionResolver;

    /**
     * @var MediaSelectionResolver|ObjectProphecy
     */
    private $mediaSelectionResolver;

    protected function setUp(): void
    {
        $this->mediaSelectionResolver = $this->prophesize(MediaSelectionResolver::class);

        $this->singleMediaSelectionResolver = new SingleMediaSelectionResolver(
            $this->mediaSelectionResolver->reveal()
        );
    }

    public function testGetContentType(): void
    {
        self::assertSame('single_media_selection', $this->singleMediaSelectionResolver::getContentType());
    }

    public function testResolve(): void
    {
        $property = $this->prophesize(PropertyInterface::class);
        $this->mediaSelectionResolver->resolve(['ids' => [1]], $property, 'en', [])->willReturn(
            new ContentView(
                [
                    [
                        'id' => 1,
                        'formatUri' => '/media/1/{format}/media-1.jpg?v=1-0',
                    ],
                ],
                ['ids' => [1]]
            )
        );

        $result = $this->singleMediaSelectionResolver->resolve(['id' => 1], $property->reveal(), 'en');

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame(
            [
                'id' => 1,
                'formatUri' => '/media/1/{format}/media-1.jpg?v=1-0',
            ],
            $result->getContent()
        );
        $this->assertSame(
            [
                'id' => 1,
            ],
            $result->getView()
        );
    }

    public function testResolveDataIsNull(): void
    {
        $locale = 'en';
        $property = $this->prophesize(PropertyInterface::class);

        $result = $this->singleMediaSelectionResolver->resolve(null, $property->reveal(), $locale);

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertNull($result->getContent());
        $this->assertSame([], $result->getView());
    }

    public function testResolveDataWithoutId(): void
    {
        $locale = 'en';
        $property = $this->prophesize(PropertyInterface::class);

        $this->mediaSelectionResolver->resolve(['ids' => []], $property, 'en', [])->willReturn(
            new ContentView([], ['ids' => []])
        );

        $dataWithoutIdKey = ['unrelatedKey' => 'unrelatedValue'];
        $result = $this->singleMediaSelectionResolver->resolve($dataWithoutIdKey, $property->reveal(), $locale);

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertNull($result->getContent());
        $this->assertSame($dataWithoutIdKey, $result->getView());
    }
}
