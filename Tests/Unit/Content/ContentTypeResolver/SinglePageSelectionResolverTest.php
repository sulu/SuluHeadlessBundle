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
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\PageSelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\SinglePageSelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Component\Content\Compat\PropertyInterface;

class SinglePageSelectionResolverTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var SinglePageSelectionResolver
     */
    private $singlePageSelectionResolver;

    /**
     * @var PageSelectionResolver|ObjectProphecy
     */
    private $pageSelectionResolver;

    protected function setUp(): void
    {
        $this->pageSelectionResolver = $this->prophesize(PageSelectionResolver::class);

        $this->singlePageSelectionResolver = new SinglePageSelectionResolver(
            $this->pageSelectionResolver->reveal()
        );
    }

    public function testGetContentType(): void
    {
        self::assertSame('single_page_selection', $this->singlePageSelectionResolver::getContentType());
    }

    public function testResolve(): void
    {
        $property = $this->prophesize(PropertyInterface::class);
        $uuid = '2c55ea29-a5ba-4847-90ce-038b86384ab5';
        $this->pageSelectionResolver->resolve([$uuid], $property, 'en', [])->willReturn(
            new ContentView(
                [
                    [
                        'id' => $uuid,
                        'uuid' => $uuid,
                        'nodeType' => 1,
                        'path' => '/testpage',
                        'changer' => 1,
                        'publishedState' => true,
                        'creator' => 1,
                        'title' => 'TestPage',
                        'locale' => 'en',
                        'webspaceKey' => 'sulu',
                        'template' => 'headless',
                        'parent' => '1',
                        'author' => '2',
                    ],
                ],
                [1]
            )
        );

        $result = $this->singlePageSelectionResolver->resolve($uuid, $property->reveal(), 'en');

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame(
            [
                'id' => $uuid,
                'uuid' => $uuid,
                'nodeType' => 1,
                'path' => '/testpage',
                'changer' => 1,
                'publishedState' => true,
                'creator' => 1,
                'title' => 'TestPage',
                'locale' => 'en',
                'webspaceKey' => 'sulu',
                'template' => 'headless',
                'parent' => '1',
                'author' => '2',
            ],
            $result->getContent()
        );
        $this->assertSame(
            [
                'id' => $uuid,
            ],
            $result->getView()
        );
    }

    public function testResolveDataIsNull(): void
    {
        $locale = 'en';
        $property = $this->prophesize(PropertyInterface::class);

        $result = $this->singlePageSelectionResolver->resolve(null, $property->reveal(), $locale);

        $this->assertNull($result->getContent());

        $this->assertSame(['id' => null], $result->getView());
    }
}
