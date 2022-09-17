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

namespace Sulu\Bundle\HeadlessBundle\Content\Serializer;

use Sulu\Bundle\MediaBundle\Api\Collection;
use Sulu\Bundle\MediaBundle\Entity\CollectionInterface;

class CollectionSerializer implements CollectionSerializerInterface
{
    public function serialize(CollectionInterface $collection, string $locale): array
    {
        $apiCollection = new Collection($collection, $locale);

        return [
            'id' => $apiCollection->getId(),
            'key' => $apiCollection->getKey(),
            'title' => $apiCollection->getTitle(),
            'description' => $apiCollection->getDescription(),
        ];
    }
}
