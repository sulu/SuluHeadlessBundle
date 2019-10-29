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

namespace Sulu\Bundle\HeadlessBundle\Tests\Unit\Content\DataProviderResolver;

use PHPUnit\Framework\TestCase;
use Sulu\Bundle\HeadlessBundle\Content\DataProviderResolver\DataProviderResult;

class DataProviderResultTest extends TestCase
{
    public function testGetHasNextPage(): void
    {
        $result = new DataProviderResult([['id' => 'id-1'], ['id' => 'id-2']], true);

        $this->assertTrue($result->getHasNextPage());
    }

    public function testGetItems(): void
    {
        $result = new DataProviderResult([['id' => 'id-1'], ['id' => 'id-2']], true);

        $this->assertSame([['id' => 'id-1'], ['id' => 'id-2']], $result->getItems());
    }
}
