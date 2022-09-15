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

use Sulu\Bundle\MediaBundle\Entity\CollectionInterface;

interface CollectionSerializerInterface
{
    /**
     * @return array{
     *     id: int,
     *     key: string,
     *     title: string|null,
     *     description: string|null,
     *     locale: string,
     * }
     */
    public function serialize(CollectionInterface $collection, string $locale): array;
}
