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
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\AdminBundle\SmartContent\Configuration\ProviderConfigurationInterface;
use Sulu\Bundle\AdminBundle\SmartContent\SmartContentProviderInterface;
use Sulu\Bundle\HeadlessBundle\Content\DataProviderResolver\MediaDataProviderResolver;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\MediaSerializerInterface;
use Sulu\Bundle\MediaBundle\Entity\MediaInterface;
use Sulu\Bundle\MediaBundle\Entity\MediaRepositoryInterface;

class MediaDataProviderResolverTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var ObjectProphecy<SmartContentProviderInterface>
     */
    private ObjectProphecy $mediaSmartContentProvider;

    /**
     * @var ObjectProphecy<MediaSerializerInterface>
     */
    private ObjectProphecy $mediaSerializer;

    /**
     * @var ObjectProphecy<MediaRepositoryInterface>
     */
    private ObjectProphecy $mediaRepository;

    private MediaDataProviderResolver $mediaResolver;

    protected function setUp(): void
    {
        $this->mediaSmartContentProvider = $this->prophesize(SmartContentProviderInterface::class);
        $this->mediaSerializer = $this->prophesize(MediaSerializerInterface::class);
        $this->mediaRepository = $this->prophesize(MediaRepositoryInterface::class);

        $this->mediaResolver = new MediaDataProviderResolver(
            $this->mediaSmartContentProvider->reveal(),
            $this->mediaSerializer->reveal(),
            $this->mediaRepository->reveal(),
        );
    }

    public function testGetDataProvider(): void
    {
        self::assertSame('media', $this->mediaResolver::getDataProvider());
    }

    public function testGetProviderConfiguration(): void
    {
        $configuration = $this->prophesize(ProviderConfigurationInterface::class);
        $this->mediaSmartContentProvider->getConfiguration()->willReturn($configuration->reveal());

        $this->assertSame($configuration->reveal(), $this->mediaResolver->getProviderConfiguration());
    }

    public function testGetProviderDefaultParams(): void
    {
        $this->assertSame([], $this->mediaResolver->getProviderDefaultParams());
    }

    public function testResolve(): void
    {
        $media1 = $this->prophesize(MediaInterface::class);
        $media1->getId()->willReturn(1);
        $media2 = $this->prophesize(MediaInterface::class);
        $media2->getId()->willReturn(2);

        // SmartContentProvider returns flat results with id/title
        $this->mediaSmartContentProvider->findFlatBy(
            Argument::type('array'),
            Argument::type('array'),
            ['locale' => 'en']
        )->willReturn([
            ['id' => '1', 'title' => 'Media 1'],
            ['id' => '2', 'title' => 'Media 2'],
        ]);

        // Repository fetches actual entities in batch
        $this->mediaRepository->findMedia(['ids' => [1, 2]])->willReturn([
            $media1->reveal(),
            $media2->reveal(),
        ]);

        $this->mediaSerializer->serialize($media1, 'en')->willReturn([
            'id' => 1,
            'formatUri' => '/media/1/{format}/media-1.jpg?v=1-0',
        ]);

        $this->mediaSerializer->serialize($media2, 'en')->willReturn([
            'id' => 2,
            'formatUri' => '/media/2/{format}/media-2.jpg?v=1-0',
        ]);

        $result = $this->mediaResolver->resolve(['dataSource' => '1'], [], ['locale' => 'en'], 10, 1, 5);

        // hasNextPage is false because count(2) < pageSize(5)
        $this->assertFalse($result->getHasNextPage());
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

    public function testResolveWithoutDataSourceReturnsEmpty(): void
    {
        // When no dataSource is provided, the resolver should return empty without calling the provider
        $result = $this->mediaResolver->resolve([], [], ['locale' => 'en'], 10, 1, 5);

        $this->assertFalse($result->getHasNextPage());
        $this->assertSame([], $result->getItems());
    }

    public function testResolveEmptyResultFromProvider(): void
    {
        $this->mediaSmartContentProvider->findFlatBy(
            Argument::type('array'),
            Argument::type('array'),
            ['locale' => 'en']
        )->willReturn([]);

        $result = $this->mediaResolver->resolve(['dataSource' => '1'], [], ['locale' => 'en'], 10, 1, 5);

        $this->assertFalse($result->getHasNextPage());
        $this->assertSame([], $result->getItems());
    }
}
