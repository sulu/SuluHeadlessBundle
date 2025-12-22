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
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\ContentTypeResolverInterface;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\SingleSnippetSelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;

class SingleSnippetSelectionResolverTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var ObjectProphecy<ContentTypeResolverInterface>
     */
    private ObjectProphecy $snippetSelectionResolver;

    private SingleSnippetSelectionResolver $singleSnippetSelectionResolver;

    private FieldMetadata $fieldMetadata;

    protected function setUp(): void
    {
        $this->snippetSelectionResolver = $this->prophesize(ContentTypeResolverInterface::class);
        $this->fieldMetadata = new FieldMetadata('snippet');

        $this->singleSnippetSelectionResolver = new SingleSnippetSelectionResolver(
            $this->snippetSelectionResolver->reveal(),
        );
    }

    public function testGetContentType(): void
    {
        self::assertSame('single_snippet_selection', $this->singleSnippetSelectionResolver::getContentType());
    }

    public function testResolve(): void
    {
        $locale = 'en';

        $this->snippetSelectionResolver->resolve(
            ['snippet-1'],
            $this->fieldMetadata,
            $locale,
            [],
        )->willReturn(new ContentView(
            [
                [
                    'id' => 'snippet-1',
                    'template' => 'test',
                    'content' => [],
                    'view' => [],
                ],
            ],
            ['ids' => ['snippet-1']],
        ));

        $result = $this->singleSnippetSelectionResolver->resolve('snippet-1', $this->fieldMetadata, $locale, []);

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame(
            [
                'id' => 'snippet-1',
                'template' => 'test',
                'content' => [],
                'view' => [],
            ],
            $result->getContent()
        );

        $this->assertSame(
            ['id' => 'snippet-1'],
            $result->getView()
        );
    }

    public function testResolveDataIsNull(): void
    {
        $locale = 'en';

        $this->snippetSelectionResolver->resolve(
            null,
            $this->fieldMetadata,
            $locale,
            [],
        )->willReturn(new ContentView([], ['ids' => []]));

        $result = $this->singleSnippetSelectionResolver->resolve(null, $this->fieldMetadata, $locale, []);

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertNull($result->getContent());
        $this->assertSame(['id' => null], $result->getView());
    }

    public function testResolveDataIsEmptyString(): void
    {
        $locale = 'en';

        $this->snippetSelectionResolver->resolve(
            null,
            $this->fieldMetadata,
            $locale,
            [],
        )->willReturn(new ContentView([], ['ids' => []]));

        $result = $this->singleSnippetSelectionResolver->resolve('', $this->fieldMetadata, $locale, []);

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertNull($result->getContent());
        $this->assertSame(['id' => null], $result->getView());
    }

    public function testResolveWithDefaultSnippet(): void
    {
        $locale = 'en';
        $webspaceKey = 'sulu_io';

        $this->snippetSelectionResolver->resolve(
            null,
            $this->fieldMetadata,
            $locale,
            ['webspaceKey' => $webspaceKey],
        )->willReturn(new ContentView(
            [
                [
                    'id' => 'default-snippet-1',
                    'template' => 'test',
                    'content' => [],
                    'view' => [],
                ],
            ],
            ['ids' => ['default-snippet-1']],
        ));

        $result = $this->singleSnippetSelectionResolver->resolve(null, $this->fieldMetadata, $locale, ['webspaceKey' => $webspaceKey]);

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame(
            [
                'id' => 'default-snippet-1',
                'template' => 'test',
                'content' => [],
                'view' => [],
            ],
            $result->getContent()
        );

        $this->assertSame(
            ['id' => 'default-snippet-1'],
            $result->getView()
        );
    }
}
