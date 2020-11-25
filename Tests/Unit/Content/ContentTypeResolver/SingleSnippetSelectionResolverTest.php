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
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\SingleSnippetSelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\StructureResolverInterface;
use Sulu\Bundle\SnippetBundle\Document\SnippetDocument;
use Sulu\Bundle\SnippetBundle\Snippet\DefaultSnippetManagerInterface;
use Sulu\Component\Content\Compat\PropertyInterface;
use Sulu\Component\Content\Compat\PropertyParameter;
use Sulu\Component\Content\Compat\Structure\SnippetBridge;
use Sulu\Component\Content\Compat\Structure\StructureBridge;
use Sulu\Component\Content\Mapper\ContentMapperInterface;

class SingleSnippetSelectionResolverTest extends TestCase
{
    /**
     * @var ObjectProphecy|ContentMapperInterface
     */
    private $contentMapper;

    /**
     * @var ObjectProphecy|StructureResolverInterface
     */
    private $structureResolver;

    /**
     * @var ObjectProphecy|DefaultSnippetManagerInterface
     */
    private $defaultSnippetManager;

    /**
     * @var SingleSnippetSelectionResolver
     */
    private $snippetSelectionResolver;

    protected function setUp(): void
    {
        $this->contentMapper = $this->prophesize(ContentMapperInterface::class);
        $this->structureResolver = $this->prophesize(StructureResolverInterface::class);
        $this->defaultSnippetManager = $this->prophesize(DefaultSnippetManagerInterface::class);

        $this->snippetSelectionResolver = new SingleSnippetSelectionResolver(
            $this->contentMapper->reveal(),
            $this->structureResolver->reveal(),
            $this->defaultSnippetManager->reveal()
        );
    }

    public function testGetContentType(): void
    {
        self::assertSame('single_snippet_selection', $this->snippetSelectionResolver::getContentType());
    }

    public function testResolveWithExcerpt(): void
    {
        $structure = $this->prophesize(StructureBridge::class);
        $structure->getWebspaceKey()->willReturn('webspace-key');
        $structure->getIsShadow()->willReturn(false);

        $property = $this->prophesize(PropertyInterface::class);
        $property->getStructure()->willReturn($structure->reveal());
        $property->getParams()->willReturn([
            'loadExcerpt' => new PropertyParameter('loadExcerpt', true),
        ]);

        $snippet1 = $this->prophesize(SnippetBridge::class);
        $snippet1->getHasTranslation()->willReturn(true);
        $snippet1->setIsShadow(false)->shouldBeCalled();
        $snippet1->setShadowBaseLanguage(null)->shouldBeCalled();

        $this->contentMapper->load('snippet-1', 'webspace-key', 'en')->willReturn($snippet1->reveal());
        $this->structureResolver->resolve($snippet1->reveal(), 'en', true)->willReturn([
            'id' => 'snippet-1',
            'template' => 'test',
            'content' => [],
            'view' => [],
        ]);

        $result = $this->snippetSelectionResolver->resolve('snippet-1', $property->reveal(), 'en', []);
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

    public function testResolveShadowLocale(): void
    {
        $structure = $this->prophesize(StructureBridge::class);
        $structure->getWebspaceKey()->willReturn('webspace-key');
        $structure->getIsShadow()->willReturn(true);
        $structure->getShadowBaseLanguage()->willReturn('de');

        $property = $this->prophesize(PropertyInterface::class);
        $property->getStructure()->willReturn($structure->reveal());
        $property->getParams()->willReturn([]);

        $snippet1 = $this->prophesize(SnippetBridge::class);
        $snippet1->getHasTranslation()->willReturn(true);
        $snippet1->setIsShadow(true)->shouldBeCalled();
        $snippet1->setShadowBaseLanguage('de')->shouldBeCalled();

        $this->contentMapper->load('snippet-1', 'webspace-key', 'en')->willReturn($snippet1->reveal());
        $this->structureResolver->resolve($snippet1->reveal(), 'en', false)->willReturn([
            'id' => 'snippet-1',
            'template' => 'test',
            'content' => [],
            'view' => [],
        ]);

        $result = $this->snippetSelectionResolver->resolve('snippet-1', $property->reveal(), 'en', []);
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

    public function testResolveShadowLocaleNoTranslation(): void
    {
        $structure = $this->prophesize(StructureBridge::class);
        $structure->getWebspaceKey()->willReturn('webspace-key');
        $structure->getIsShadow()->willReturn(true);
        $structure->getShadowBaseLanguage()->willReturn('de');

        $property = $this->prophesize(PropertyInterface::class);
        $property->getStructure()->willReturn($structure->reveal());
        $property->getParams()->willReturn([]);

        $snippet1en = $this->prophesize(SnippetBridge::class);
        $snippet1en->getHasTranslation()->willReturn(false);

        $snippet1de = $this->prophesize(SnippetBridge::class);
        $snippet1de->setIsShadow(true)->shouldBeCalled();
        $snippet1de->setShadowBaseLanguage('de')->shouldBeCalled();

        $this->contentMapper->load('snippet-1', 'webspace-key', 'en')->willReturn($snippet1en->reveal());
        $this->contentMapper->load('snippet-1', 'webspace-key', 'de')->willReturn($snippet1de->reveal());
        $this->structureResolver->resolve($snippet1de->reveal(), 'en', false)->willReturn([
            'id' => 'snippet-1',
            'template' => 'test',
            'content' => [],
            'view' => [],
        ]);

        $result = $this->snippetSelectionResolver->resolve('snippet-1', $property->reveal(), 'en', []);
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
        $structure = $this->prophesize(StructureBridge::class);
        $structure->getWebspaceKey()->willReturn('webspace-key');
        $structure->getIsShadow()->willReturn(false);

        $property = $this->prophesize(PropertyInterface::class);
        $property->getStructure()->willReturn($structure->reveal());
        $property->getParams()->willReturn([]);

        $result = $this->snippetSelectionResolver->resolve(null, $property->reveal(), 'en', []);
        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertNull(
            $result->getContent()
        );

        $this->assertSame(
            ['id' => null],
            $result->getView()
        );
    }

    public function testResolveDataIsNullWithDefaultArea(): void
    {
        $structure = $this->prophesize(StructureBridge::class);
        $structure->getWebspaceKey()->willReturn('webspace-key');
        $structure->getIsShadow()->willReturn(false);

        $property = $this->prophesize(PropertyInterface::class);
        $property->getStructure()->willReturn($structure->reveal());
        $property->getParams()->willReturn([
            'default' => new PropertyParameter('default', 'test-snippet-area'),
        ]);

        $defaultSnippetDocument = $this->prophesize(SnippetDocument::class);
        $defaultSnippetDocument->getUuid()->willReturn('default-snippet-1');
        $this->defaultSnippetManager->load('webspace-key', 'test-snippet-area', 'en')
                ->willReturn($defaultSnippetDocument->reveal());

        $defaultSnippet = $this->prophesize(SnippetBridge::class);
        $defaultSnippet->getHasTranslation()->willReturn(true);
        $defaultSnippet->setIsShadow(false)->shouldBeCalled();
        $defaultSnippet->setShadowBaseLanguage(null)->shouldBeCalled();

        $this->contentMapper->load('default-snippet-1', 'webspace-key', 'en')->willReturn($defaultSnippet->reveal());
        $this->structureResolver->resolve($defaultSnippet->reveal(), 'en', false)->willReturn([
            'id' => 'default-snippet-1',
            'template' => 'test',
            'content' => [],
            'view' => [],
        ]);

        $result = $this->snippetSelectionResolver->resolve(null, $property->reveal(), 'en', []);
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
            ['id' => null],
            $result->getView()
        );
    }
}
