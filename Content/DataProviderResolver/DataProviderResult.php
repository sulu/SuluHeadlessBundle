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

namespace Sulu\Bundle\HeadlessBundle\Content\DataProviderResolver;

class DataProviderResult
{
    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function __construct(
        private array $items,
        private bool $hasNextPage,
    ) {
    }

    public function getHasNextPage(): bool
    {
        return $this->hasNextPage;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getItems(): array
    {
        return $this->items;
    }
}
