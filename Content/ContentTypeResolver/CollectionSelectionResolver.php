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

namespace Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver;

use Doctrine\Persistence\ObjectRepository;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\CollectionSerializerInterface;
use Sulu\Bundle\MediaBundle\Entity\CollectionInterface;
use Sulu\Bundle\MediaBundle\Entity\CollectionRepositoryInterface;
use Sulu\Component\Content\Compat\PropertyInterface;

class CollectionSelectionResolver implements ContentTypeResolverInterface
{
    public static function getContentType(): string
    {
        return 'collection_selection';
    }

    /**
     * @var CollectionRepositoryInterface
     */
    private $collectionRepository;

    /**
     * @var CollectionSerializerInterface
     */
    private $collectionSerializer;

    public function __construct(
        CollectionRepositoryInterface $collectionRepository,
        CollectionSerializerInterface $collectionSerializer
    ) {
        $this->collectionRepository = $collectionRepository;
        $this->collectionSerializer = $collectionSerializer;
    }

    public function resolve($data, PropertyInterface $property, string $locale, array $attributes = []): ContentView
    {
        /** @var int[]|null $ids */
        $ids = $data;

        if (empty($ids)) {
            return new ContentView([], ['ids' => []]);
        }

        if ($this->collectionRepository instanceof ObjectRepository) {
            /** @var iterable<CollectionInterface> $collections */
            $collections = $this->collectionRepository->findBy(['id' => $ids]);
        } else {
            $collections = [];
            foreach ($ids as $id) {
                /** @var CollectionInterface|null $collection */
                $collection = $this->collectionRepository->findCollectionById($id);

                if ($collection) {
                    $collections[] = $collection;
                }
            }
        }

        $serializedCollections = \array_fill_keys($ids, null);

        foreach ($collections as $collection) {
            $serializedCollections[$collection->getId()] = $this->collectionSerializer->serialize($collection, $locale);
        }

        return new ContentView(\array_values(\array_filter($serializedCollections)), ['ids' => $ids]);
    }
}
