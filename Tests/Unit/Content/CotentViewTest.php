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

namespace Sulu\Bundle\HeadlessBundle\Tests\Unit\Content;

use PHPUnit\Framework\TestCase;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;

class CotentViewTest extends TestCase
{
    public function testGetContent(): void
    {
        $contentView = new ContentView('Test-123');

        $this->assertSame('Test-123', $contentView->getContent());
    }

    public function testGetViewDefault(): void
    {
        $contentView = new ContentView('Test-123');

        $this->assertSame([], $contentView->getView());
    }

    public function testGetView(): void
    {
        $contentView = new ContentView('Test-123', ['test' => 'test']);

        $this->assertSame(['test' => 'test'], $contentView->getView());
    }
}
