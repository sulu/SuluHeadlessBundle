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
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\TextEditorResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\MarkupBundle\Markup\MarkupParserInterface;
use Sulu\Component\Content\Compat\PropertyInterface;

class TextEditorResolverTest extends TestCase
{
    /**
     * @var MarkupParserInterface|ObjectProphecy
     */
    private $markupParser;

    /**
     * @var TextEditorResolver
     */
    private $textEditorResolver;

    protected function setUp(): void
    {
        $this->markupParser = $this->prophesize(MarkupParserInterface::class);

        $this->textEditorResolver = new TextEditorResolver(
            $this->markupParser->reveal()
        );
    }

    public function testGetContentType(): void
    {
        self::assertSame('text_editor', $this->textEditorResolver::getContentType());
    }

    public function testResolve(): void
    {
        $data = '<p>Text with a <sulu-link href="bdb4fa4a-b780-4c61-9591-8e6e029b20c8" target="_self" provider="page">link</sulu-link>.</p>';
        $property = $this->prophesize(PropertyInterface::class);

        $this->markupParser->parse($data, 'en')
            ->willReturn('<p>Text with a <a href="http://headless.localhost:8004/other-page" target="_self" title="Other Page">link</a>.</p>');

        $result = $this->textEditorResolver->resolve($data, $property->reveal(), 'en');

        $this->assertInstanceOf(ContentView::class, $result);

        $this->assertSame(
            '<p>Text with a <a href="http://headless.localhost:8004/other-page" target="_self" title="Other Page">link</a>.</p>',
            $result->getContent()
        );

        $this->assertSame(
            [],
            $result->getView()
        );
    }

    public function testResolveDataIsNull(): void
    {
        $property = $this->prophesize(PropertyInterface::class);

        $result = $this->textEditorResolver->resolve(null, $property->reveal(), 'en');

        $this->assertNull($result->getContent());

        $this->assertSame([], $result->getView());
    }

    public function testResolveDataIsEmptyString(): void
    {
        $property = $this->prophesize(PropertyInterface::class);

        $this->markupParser->parse('', 'en')->willReturn('');

        $result = $this->textEditorResolver->resolve('', $property->reveal(), 'en');

        $this->assertSame('', $result->getContent());

        $this->assertSame([], $result->getView());
    }
}
