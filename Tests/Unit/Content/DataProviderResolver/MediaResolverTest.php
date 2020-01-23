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
use Sulu\Bundle\HeadlessBundle\Content\DataProviderResolver\MediaResolver;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\MediaSerializer;
use Sulu\Bundle\MediaBundle\Api\Media;
use Sulu\Component\Content\Compat\PropertyParameter;
use Sulu\Component\Media\SmartContent\MediaDataProvider;
use Sulu\Component\SmartContent\Configuration\ProviderConfigurationInterface;
use Sulu\Component\SmartContent\DataProviderResult;
use Sulu\Component\SmartContent\ResourceItemInterface;

class MediaResolverTest extends TestCase
{
    /**
     * @var MediaDataProvider|ObjectProphecy
     */
    private $mediaDataProvider;

    /**
     * @var MediaSerializer|ObjectProphecy
     */
    private $mediaSerializer;

    /**
     * @var MediaResolver
     */
    private $mediaResolver;

    protected function setUp(): void
    {
        $this->mediaDataProvider = $this->prophesize(MediaDataProvider::class);
        $this->mediaSerializer = $this->prophesize(MediaSerializer::class);

        $this->mediaResolver = new MediaResolver(
            $this->mediaDataProvider->reveal(),
            $this->mediaSerializer->reveal()
        );
    }

    public function testGetContentType(): void
    {
        self::assertSame('media', $this->mediaResolver::getDataProvider());
    }

    public function testGetProviderConfiguration(): void
    {
        $configuration = $this->prophesize(ProviderConfigurationInterface::class);
        $this->mediaDataProvider->getConfiguration()->willReturn($configuration->reveal());

        $this->assertSame($configuration->reveal(), $this->mediaResolver->getProviderConfiguration());
    }

    public function testGetProviderDefaultParams(): void
    {
        $propertyParameter = $this->prophesize(PropertyParameter::class);
        $this->mediaDataProvider->getDefaultPropertyParameter()->willReturn(['test' => $propertyParameter->reveal()]);

        $this->assertSame(['test' => $propertyParameter->reveal()], $this->mediaResolver->getProviderDefaultParams());
    }

    public function testResolve(): void
    {
        $media1 = $this->prophesize(Media::class);
        $media1->getId()->willReturn(1);
        $media1->getName()->willReturn('media-1');
        $media1->getVersion()->willReturn(1);
        $media1->getSubVersion()->willReturn(0);

        $media2 = $this->prophesize(Media::class);
        $media2->getId()->willReturn(2);
        $media2->getName()->willReturn('media-2');
        $media2->getVersion()->willReturn(1);
        $media2->getSubVersion()->willReturn(0);

        $resourceItem1 = $this->prophesize(ResourceItemInterface::class);
        $resourceItem1->getResource()->willReturn($media1->reveal());

        $resourceItem2 = $this->prophesize(ResourceItemInterface::class);
        $resourceItem2->getResource()->willReturn($media2->reveal());

        $providerResult = $this->prophesize(DataProviderResult::class);
        $providerResult->getHasNextPage()->willReturn(true);
        $providerResult->getItems()->willReturn([$resourceItem1, $resourceItem2]);

        $this->mediaDataProvider->resolveResourceItems([], [], [], 10, 1, 5)->willReturn($providerResult->reveal());

        $this->mediaSerializer->serialize($media1)->willReturn([
            'id' => 1,
            'formatUri' => '/media/1/{format}/media-1.jpg?v=1-0',
        ]);

        $this->mediaSerializer->serialize($media2)->willReturn([
            'id' => 2,
            'formatUri' => '/media/2/{format}/media-2.jpg?v=1-0',
        ]);

        $result = $this->mediaResolver->resolve([], [], [], 10, 1, 5);

        $this->assertTrue($result->getHasNextPage());
        $this->assertSame(
            [
                [
                    'id' => 1,
                    'formatUri' => '/media/1/{format}/media-1.jpg?v=1-0',
                ],
                [
                    'id' => 2,
                    'formatUri' => '/media/2/{format}/media-2.jpg?v=1-0',
                ],
            ],
            $result->getItems()
        );
    }
}
