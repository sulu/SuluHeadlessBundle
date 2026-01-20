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
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\PageSelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\SinglePageSelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;

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

    private FieldMetadata $fieldMetadata;

    protected function setUp(): void
    {
        $this->pageSelectionResolver = $this->prophesize(PageSelectionResolver::class);
        $this->fieldMetadata = new FieldMetadata('page');

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
        $uuid = '2c55ea29-a5ba-4847-90ce-038b86384ab5';
        $this->pageSelectionResolver->resolve([$uuid], $this->fieldMetadata, 'en', [])->willReturn(
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

        $result = $this->singlePageSelectionResolver->resolve($uuid, $this->fieldMetadata, 'en');

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

        $result = $this->singlePageSelectionResolver->resolve(null, $this->fieldMetadata, $locale);

        $this->assertNull($result->getContent());

        $this->assertSame(['id' => ''], $result->getView());
    }
}
