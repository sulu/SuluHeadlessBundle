<?php

declare(strict_types=1);


namespace Sulu\Bundle\HeadlessBundle\Tests\Unit\Content\ContentTypeResolver;

use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\SnippetSelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\StructureResolverInterface;
use Sulu\Component\Content\Compat\PropertyInterface;
use Sulu\Component\Content\Compat\Structure\StructureBridge;
use Sulu\Component\Content\Mapper\ContentMapperInterface;

class SnippetSelectionResolverTest extends TestCase {


    /**
     * @var ObjectProphecy
     */
    private $contentMapper;

    /**
     * @var ObjectProphecy
     */
    private $contentResolver;

    /**
     * @var SnippetSelectionResolver
     */
    private $snippetSelectionResolver;

    protected function setUp(): void {
        $this->contentMapper = $this->prophesize(ContentMapperInterface::class);
        $this->contentResolver = $this->prophesize(StructureResolverInterface::class);

        $this->snippetSelectionResolver = new SnippetSelectionResolver($this->contentMapper->reveal(), $this->contentResolver->reveal());
    }

    public function testGetContentType(): void {

        self::assertSame('snippet_selection', $this->snippetSelectionResolver::getContentType());

    }

    public function testResolveWithOutIds(): void {
        $property = $this->prophesize(PropertyInterface::class);

        $structure = $this->prophesize(StructureBridge::class);
        $structure->getIsShadow()->willReturn(false);

        $property->getStructure()->willReturn($structure->reveal());

        $result = $this->snippetSelectionResolver->resolve(null,$property->reveal(),'en',[]);
        self::assertEquals(new ContentView([]),$result);

        $result = $this->snippetSelectionResolver->resolve([],$property->reveal(),'en',[]);

        self::assertEquals(new ContentView([]),$result);
    }





}
