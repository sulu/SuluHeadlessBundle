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
use Sulu\Bundle\HeadlessBundle\Content\DataProviderResolver\PageResolver;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\PageSerializer;
use Sulu\Component\Content\Compat\PropertyParameter;
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
     * @var PageSerializer|ObjectProphecy
     */
    private $pageSerializer;

    /**
     * @var PageResolver
     */
    private $pageResolver;

    protected function setUp(): void
    {
        $this->pageDataProvider = $this->prophesize(PageDataProvider::class);
        $this->pageSerializer = $this->prophesize(PageSerializer::class);

        $this->pageResolver = new PageResolver(
            $this->pageDataProvider->reveal(),
            $this->pageSerializer->reveal(),
        );
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
        $jsonData = [
            'id' => '123-123-123',
            'template' => 'default',
            'locale' => 'de',
            'webspaceKey' => 'sulu_io',
            'contentTitle' => 'This is a title',
            'excerptTitle' => 'This is a excerpt title',
        ];
        $item->jsonSerialize()->willReturn($jsonData);

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

        $this->pageSerializer->serialize($jsonData, $properties->getValue())->willReturn([
            'id' => '123-123-123',
            'template' => 'default',
            'locale' => 'de',
            'webspaceKey' => 'sulu_io',
            'contentTitle' => 'This is another title',
            'excerptTitle' => 'This is another excerpt title',
        ]);

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
