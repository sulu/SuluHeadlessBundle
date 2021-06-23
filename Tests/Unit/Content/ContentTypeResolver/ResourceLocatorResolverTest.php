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
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\ResourceLocatorResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Component\Content\Compat\PropertyInterface;
use Sulu\Component\Content\Compat\Structure\StructureBridge;
use Sulu\Component\Content\Compat\StructureInterface;

class ResourceLocatorResolverTest extends TestCase
{
    /**
     * @var ResourceLocatorResolver
     */
    private $resourceLocatorResolver;

    protected function setUp(): void
    {
        $this->resourceLocatorResolver = new ResourceLocatorResolver();
    }

    public function testGetContentType(): void
    {
        self::assertSame('resource_locator', $this->resourceLocatorResolver::getContentType());
    }

    public function testResolve(): void
    {
        /** @var ObjectProphecy|StructureInterface $structure */
        $structure = $this->prophesize(StructureInterface::class);
        /** @var ObjectProphecy|PropertyInterface $property */
        $property = $this->prophesize(PropertyInterface::class);
        $property->getStructure()->willReturn($structure->reveal());

        $result = $this->resourceLocatorResolver->resolve('/page', $property->reveal(), 'en');

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame('/page', $result->getContent());
        $this->assertSame([], $result->getView());
    }

    public function testResolveWithStructureBridge(): void
    {
        /** @var ObjectProphecy|StructureBridge $structure */
        $structure = $this->prophesize(StructureBridge::class);
        $structure->getResourceLocator()->willReturn('/other-page');
        /** @var ObjectProphecy|PropertyInterface $property */
        $property = $this->prophesize(PropertyInterface::class);
        $property->getStructure()->willReturn($structure->reveal());

        $result = $this->resourceLocatorResolver->resolve('/page', $property->reveal(), 'en');

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame('/other-page', $result->getContent());
        $this->assertSame([], $result->getView());
    }

    public function testResolveWithStructureBridgeResourceLocatorIsNull(): void
    {
        /** @var ObjectProphecy|StructureBridge $structure */
        $structure = $this->prophesize(StructureBridge::class);
        $structure->getResourceLocator()->willReturn(null);
        /** @var ObjectProphecy|PropertyInterface $property */
        $property = $this->prophesize(PropertyInterface::class);
        $property->getStructure()->willReturn($structure->reveal());

        $result = $this->resourceLocatorResolver->resolve('/page', $property->reveal(), 'en');

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertNull($result->getContent());
        $this->assertSame([], $result->getView());
    }

    public function testResolveDataIsNull(): void
    {
        /** @var ObjectProphecy|StructureInterface $structure */
        $structure = $this->prophesize(StructureInterface::class);
        /** @var ObjectProphecy|PropertyInterface $property */
        $property = $this->prophesize(PropertyInterface::class);
        $property->getStructure()->willReturn($structure->reveal());

        $result = $this->resourceLocatorResolver->resolve(null, $property->reveal(), 'en');

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertNull($result->getContent());
        $this->assertSame([], $result->getView());
    }

    public function testResolveWithStructureBridgeDataIsNull(): void
    {
        /** @var ObjectProphecy|StructureBridge $structure */
        $structure = $this->prophesize(StructureBridge::class);
        $structure->getResourceLocator()->willReturn('/other-page');
        /** @var ObjectProphecy|PropertyInterface $property */
        $property = $this->prophesize(PropertyInterface::class);
        $property->getStructure()->willReturn($structure->reveal());

        $result = $this->resourceLocatorResolver->resolve(null, $property->reveal(), 'en');

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame('/other-page', $result->getContent());
        $this->assertSame([], $result->getView());
    }
}
