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
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\CollectionSelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\CollectionSerializerInterface;
use Sulu\Bundle\MediaBundle\Entity\CollectionInterface;
use Sulu\Bundle\MediaBundle\Entity\CollectionRepository;
use Sulu\Component\Content\Compat\PropertyInterface;

class CollectionSelectionResolverTest extends TestCase
{
    /**
     * @var ObjectProphecy<CollectionRepository>
     */
    private $collectionRepository;

    /**
     * @var ObjectProphecy<CollectionSerializerInterface>
     */
    private $collectionSerializer;

    /**
     * @var CollectionSelectionResolver
     */
    private $collectionSelectionResolver;

    protected function setUp(): void
    {
        $this->collectionRepository = $this->prophesize(CollectionRepository::class);
        $this->collectionSerializer = $this->prophesize(CollectionSerializerInterface::class);

        $this->collectionSelectionResolver = new CollectionSelectionResolver(
            $this->collectionRepository->reveal(),
            $this->collectionSerializer->reveal(),
        );
    }

    public function testGetContentType(): void
    {
        self::assertSame('collection_selection', $this->collectionSelectionResolver::getContentType());
    }

    public function testResolve(): void
    {
        $locale = 'en';

        $collection1 = $this->prophesize(CollectionInterface::class);
        $collection1->getId()->willReturn(1);
        $collection2 = $this->prophesize(CollectionInterface::class);
        $collection1->getId()->willReturn(2);

        $this->collectionRepository->findBy(['id' => [1, 2]])->shouldBeCalled()->willReturn([
            $collection2->reveal(),
            $collection1->reveal(),
        ]);

        $this->collectionSerializer->serialize($collection2->reveal(), $locale)
            ->willReturn([
                'id' => 2,
                'key' => 'key-2',
                'title' => 'title-2',
                'description' => 'description-2',
            ]);

        $this->collectionSerializer->serialize($collection1->reveal(), $locale)
            ->willReturn([
                'id' => 1,
                'key' => 'key-1',
                'title' => 'title-1',
                'description' => 'description-1',
            ]);

        $property = $this->prophesize(PropertyInterface::class);

        $result = $this->collectionSelectionResolver->resolve([1, 2], $property->reveal(), $locale);

        $this->assertInstanceOf(ContentView::class, $result);

        $this->assertSame(
            [
                [
                    'id' => 1,
                    'key' => 'key-1',
                    'title' => 'title-1',
                    'description' => 'description-1',
                ],
                [
                    'id' => 2,
                    'key' => 'key-2',
                    'title' => 'title-2',
                    'description' => 'description-2',
                ],
            ],
            $result->getContent(),
        );

        $this->assertSame(
            ['ids' => [1, 2]],
            $result->getView(),
        );
    }

    public function testResolveDataIsNull(): void
    {
        $locale = 'en';
        $property = $this->prophesize(PropertyInterface::class);

        $result = $this->collectionSelectionResolver->resolve(null, $property->reveal(), $locale);

        $this->assertSame([], $result->getContent());

        $this->assertSame(['ids' => []], $result->getView());
    }

    public function testResolveDataIsEmptyArray(): void
    {
        $locale = 'en';
        $property = $this->prophesize(PropertyInterface::class);

        $result = $this->collectionSelectionResolver->resolve([], $property->reveal(), $locale);

        $this->assertSame([], $result->getContent());

        $this->assertSame(['ids' => []], $result->getView());
    }
}
