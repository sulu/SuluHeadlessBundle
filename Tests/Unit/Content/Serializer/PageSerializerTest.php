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

namespace Sulu\Bundle\HeadlessBundle\Tests\Unit\Content\Serializer;

use PHPStan\Testing\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\HeadlessBundle\Content\ContentResolverInterface;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\PageSerializer;
use Sulu\Component\Content\Compat\PropertyInterface;
use Sulu\Component\Content\Compat\PropertyParameter;
use Sulu\Component\Content\Compat\StructureInterface;
use Sulu\Component\Content\Compat\StructureManagerInterface;

class PageSerializerTest extends TestCase
{
    /**
     * @var StructureManagerInterface|ObjectProphecy
     */
    private $structureManager;

    /**
     * @var ContentResolverInterface|ObjectProphecy
     */
    private $contentResolver;

    /**
     * @var PageSerializer
     */
    private $pageSerializer;

    /**
     * @var StructureInterface|ObjectProphecy
     */
    private $defaultStructure;

    /**
     * @var StructureInterface|ObjectProphecy
     */
    private $excerptStructure;

    /**
     * @var PropertyInterface|ObjectProphecy
     */
    private $defaultTitleProperty;

    /**
     * @var PropertyInterface|ObjectProphecy
     */
    private $excerptTitleProperty;

    protected function setUp(): void
    {
        $this->structureManager = $this->prophesize(StructureManagerInterface::class);
        $this->contentResolver = $this->prophesize(ContentResolverInterface::class);

        $this->pageSerializer = new PageSerializer(
            $this->structureManager->reveal(),
            $this->contentResolver->reveal()
        );

        $this->defaultStructure = $this->prophesize(StructureInterface::class);
        $this->excerptStructure = $this->prophesize(StructureInterface::class);

        $this->defaultTitleProperty = $this->prophesize(PropertyInterface::class);
        $this->defaultStructure->getProperty('title')->willReturn($this->defaultTitleProperty->reveal());

        $this->excerptTitleProperty = $this->prophesize(PropertyInterface::class);
        $this->excerptStructure->getProperty('title')->willReturn($this->excerptTitleProperty->reveal());

        $this->structureManager->getStructure('default')->willReturn($this->defaultStructure->reveal());
        $this->structureManager->getStructure('excerpt')->willReturn($this->excerptStructure->reveal());
    }

    public function testSerialize(): void
    {
        $page = [
            'id' => '123-123-123',
            'template' => 'default',
            'locale' => 'de',
            'webspaceKey' => 'sulu_io',
            'title' => 'This is a title',
            'excerptTitle' => 'This is a excerpt title',
        ];

        $property = $this->prophesize(PropertyInterface::class);
        $params = [
            'properties' => new PropertyParameter('properties', [
                new PropertyParameter('title', 'title'),
                new PropertyParameter('excerptTitle', 'excerpt.title'),
            ]),
        ];
        $property->getParams()->willReturn($params);

        $this->defaultTitleProperty->setValue('This is a title')->shouldBeCalled();
        $this->contentResolver->resolve(
            'This is a title',
            $this->defaultTitleProperty->reveal(),
            'de',
            ['webspaceKey' => 'sulu_io']
        )->shouldBeCalled()->willReturn(new ContentView('This is another title'));

        $this->excerptTitleProperty->setValue('This is a excerpt title')->shouldBeCalled();
        $this->contentResolver->resolve(
            'This is a excerpt title',
            $this->excerptTitleProperty->reveal(),
            'de',
            ['webspaceKey' => 'sulu_io']
        )->shouldBeCalled()->willReturn(new ContentView('This is another excerpt title'));

        /** @var PropertyParameter[] $propertyParameters */
        $propertyParameters = $params['properties']->getValue();

        $result = $this->pageSerializer->serialize($page, $propertyParameters);

        $this->assertSame(
            [
                'id' => '123-123-123',
                'template' => 'default',
                'locale' => 'de',
                'webspaceKey' => 'sulu_io',
                'title' => 'This is another title',
                'excerptTitle' => 'This is another excerpt title',
            ],
            $result
        );
    }
}
