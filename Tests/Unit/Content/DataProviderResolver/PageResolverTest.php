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

namespace Sulu\Bundle\HeadlessBundle\Tests\Unit\Content\DataProviderResolver;

use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\HeadlessBundle\Content\ContentResolverInterface;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\DataProviderResolver\PageResolver;
use Sulu\Component\Content\Compat\PropertyInterface;
use Sulu\Component\Content\Compat\PropertyParameter;
use Sulu\Component\Content\Compat\StructureInterface;
use Sulu\Component\Content\Compat\StructureManagerInterface;
use Sulu\Component\Content\SmartContent\PageDataProvider;
use Sulu\Component\SmartContent\ArrayAccessItem;
use Sulu\Component\SmartContent\Configuration\ProviderConfigurationInterface;
use Sulu\Component\SmartContent\DataProviderResult;

class PageResolverTest extends TestCase
{
    /**
     * @var PageDataProvider|ObjectProphecy
     */
    private $pageDataProvider;

    /**
     * @var StructureManagerInterface|ObjectProphecy
     */
    private $structureManager;

    /**
     * @var ContentResolverInterface|ObjectProphecy
     */
    private $contentResolver;

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

    /**
     * @var PageResolver
     */
    private $pageResolver;

    protected function setUp(): void
    {
        $this->pageDataProvider = $this->prophesize(PageDataProvider::class);
        $this->structureManager = $this->prophesize(StructureManagerInterface::class);
        $this->contentResolver = $this->prophesize(ContentResolverInterface::class);

        $this->pageResolver = new PageResolver(
            $this->pageDataProvider->reveal(),
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

    public function testGetContentType(): void
    {
        self::assertSame('pages', $this->pageResolver::getDataProvider());
    }

    public function testGetProviderConfiguration(): void
    {
        $configuration = $this->prophesize(ProviderConfigurationInterface::class);
        $this->pageDataProvider->getConfiguration()->willReturn($configuration->reveal());

        $this->assertSame($configuration->reveal(), $this->pageResolver->getProviderConfiguration());
    }

    public function testGetProviderDefaultParams(): void
    {
        $propertyParameter = $this->prophesize(PropertyParameter::class);
        $this->pageDataProvider->getDefaultPropertyParameter()->willReturn(['test' => $propertyParameter->reveal()]);

        $this->assertSame(['test' => $propertyParameter->reveal()], $this->pageResolver->getProviderDefaultParams());
    }

    public function testResolve(): void
    {
        $item = $this->prophesize(ArrayAccessItem::class);
        $item->jsonSerialize()->willReturn(
            [
                'id' => '123-123-123',
                'template' => 'default',
                'locale' => 'de',
                'webspaceKey' => 'sulu_io',
                'contentTitle' => 'This is a title',
                'excerptTitle' => 'This is a excerpt title',
            ]
        );

        $providerResult = $this->prophesize(DataProviderResult::class);
        $providerResult->getHasNextPage()->willReturn(true);
        $providerResult->getItems()->willReturn([$item]);

        $properties = new PropertyParameter(
            'properties',
            [
                new PropertyParameter('contentTitle', 'title'),
                new PropertyParameter('excerptTitle', 'excerpt.title'),
            ]
        );

        $this->pageDataProvider->resolveResourceItems([], ['properties' => $properties], [], 10, 1, 5)
            ->willReturn($providerResult->reveal());

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

        $result = $this->pageResolver->resolve([], ['properties' => $properties], [], 10, 1, 5);

        $this->assertTrue($result->getHasNextPage());
        $this->assertSame(
            [
                [
                    'id' => '123-123-123',
                    'template' => 'default',
                    'locale' => 'de',
                    'webspaceKey' => 'sulu_io',
                    'contentTitle' => 'This is another title',
                    'excerptTitle' => 'This is another excerpt title',
                ],
            ],
            $result->getItems()
        );
    }
}
