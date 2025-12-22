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

namespace Sulu\Bundle\HeadlessBundle\Content;

class ContentView
{
    /**
     * @param mixed[] $view
     */
    public function __construct(
        private mixed $content,
        private array $view = [],
    ) {
    }

    public function getContent(): mixed
    {
        return $this->content;
    }

    /**
     * @return mixed[]
     */
    public function getView(): array
    {
        return $this->view;
    }
}
