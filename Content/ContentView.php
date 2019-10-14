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
     * @var mixed
     */
    private $content;

    /**
     * @var mixed[]
     */
    private $view;

    /**
     * @param mixed $content
     * @param mixed[] $view
     */
    public function __construct($content, array $view = [])
    {
        $this->content = $content;
        $this->view = $view;
    }

    /**
     * @return mixed
     */
    public function getContent()
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
