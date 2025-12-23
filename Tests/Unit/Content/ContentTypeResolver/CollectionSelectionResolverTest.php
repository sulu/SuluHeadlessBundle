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
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\CollectionSelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\CollectionSerializerInterface;
use Sulu\Bundle\MediaBundle\Entity\Collection;
use Sulu\Bundle\MediaBundle\Entity\CollectionRepository;
use Sulu\Bundle\MediaBundle\Entity\CollectionRepositoryInterface;
use Sulu\Bundle\MediaBundle\Entity\CollectionType;

class CollectionSelectionResolverTest extends TestCase
{
    use ProphecyTrait;

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

    private FieldMetadata $fieldMetadata;

    protected function setUp(): void
    {
        $this->collectionRepository = $this->prophesize(CollectionRepository::class);
        $this->collectionSerializer = $this->prophesize(CollectionSerializerInterface::class);
        $this->fieldMetadata = new FieldMetadata('collections');

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

        $collection1 = $this->createCollection(1);
        $collection2 = $this->createCollection(2);

        $this->collectionRepository->findBy(['id' => [1, 2]])->shouldBeCalled()->willReturn([
            $collection2,
            $collection1,
        ]);

        $this->collectionSerializer->serialize($collection2, $locale)
            ->willReturn([
                'id' => 2,
                'key' => 'key-2',
                'title' => 'title-2',
                'description' => 'description-2',
            ]);

        $this->collectionSerializer->serialize($collection1, $locale)
            ->willReturn([
                'id' => 1,
                'key' => 'key-1',
                'title' => 'title-1',
                'description' => 'description-1',
            ]);

        $result = $this->collectionSelectionResolver->resolve([1, 2], $this->fieldMetadata, $locale);

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

        $result = $this->collectionSelectionResolver->resolve(null, $this->fieldMetadata, $locale);

        $this->assertSame([], $result->getContent());

        $this->assertSame(['ids' => []], $result->getView());
    }

    public function testResolveDataIsEmptyArray(): void
    {
        $locale = 'en';

        $result = $this->collectionSelectionResolver->resolve([], $this->fieldMetadata, $locale);

        $this->assertSame([], $result->getContent());

        $this->assertSame(['ids' => []], $result->getView());
    }

    public function testResolveWithNonObjectRepository(): void
    {
        $locale = 'en';

        $collectionRepository = $this->prophesize(CollectionRepositoryInterface::class);
        $collection1 = $this->createCollection(1);
        $collection2 = $this->createCollection(2);

        $collectionRepository->findCollectionById(1)->shouldBeCalled()->willReturn($collection1);
        $collectionRepository->findCollectionById(2)->shouldBeCalled()->willReturn($collection2);

        $this->collectionSerializer->serialize($collection1, $locale)
            ->willReturn([
                'id' => 1,
                'key' => 'key-1',
                'title' => 'title-1',
                'description' => 'description-1',
            ]);

        $this->collectionSerializer->serialize($collection2, $locale)
            ->willReturn([
                'id' => 2,
                'key' => 'key-2',
                'title' => 'title-2',
                'description' => 'description-2',
            ]);

        $resolver = new CollectionSelectionResolver(
            $collectionRepository->reveal(),
            $this->collectionSerializer->reveal(),
        );

        $result = $resolver->resolve([1, 2], $this->fieldMetadata, $locale);

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
        $this->assertSame(['ids' => [1, 2]], $result->getView());
    }

    public function testResolveWithNonObjectRepositoryCollectionNotFound(): void
    {
        $locale = 'en';

        $collectionRepository = $this->prophesize(CollectionRepositoryInterface::class);
        $collection1 = $this->createCollection(1);

        $collectionRepository->findCollectionById(1)->shouldBeCalled()->willReturn($collection1);
        $collectionRepository->findCollectionById(999)->shouldBeCalled()->willReturn(null);

        $this->collectionSerializer->serialize($collection1, $locale)
            ->willReturn([
                'id' => 1,
                'key' => 'key-1',
                'title' => 'title-1',
                'description' => 'description-1',
            ]);

        $resolver = new CollectionSelectionResolver(
            $collectionRepository->reveal(),
            $this->collectionSerializer->reveal(),
        );

        $result = $resolver->resolve([1, 999], $this->fieldMetadata, $locale);

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame(
            [
                [
                    'id' => 1,
                    'key' => 'key-1',
                    'title' => 'title-1',
                    'description' => 'description-1',
                ],
            ],
            $result->getContent(),
        );
        $this->assertSame(['ids' => [1, 999]], $result->getView());
    }

    private function createCollection(int $id): Collection
    {
        $collection = new Collection();
        $collection->setType(new CollectionType());

        $reflection = new \ReflectionClass($collection);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setValue($collection, $id);

        return $collection;
    }
}
