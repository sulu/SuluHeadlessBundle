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
     * @var bool
     */
    private $hasNextPage;

    /**
     * @var array[]
     */
    private $items;

    /**
     * @param array[] $items
     */
    public function __construct(array $items, bool $hasNextPage)
    {
        $this->items = $items;
        $this->hasNextPage = $hasNextPage;
    }

    public function getHasNextPage(): bool
    {
        return $this->hasNextPage;
    }

    /**
     * @return array[]
     */
    public function getItems(): array
    {
        return $this->items;
    }
}
