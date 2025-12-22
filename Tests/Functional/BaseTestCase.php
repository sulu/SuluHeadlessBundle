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

namespace Sulu\Bundle\HeadlessBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\HeadlessBundle\Tests\Traits\AssertResponseContentTrait;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;

abstract class BaseTestCase extends SuluTestCase
{
    use AssertResponseContentTrait;

    protected static function getEntityManager(): EntityManagerInterface
    {
        return static::getContainer()->get('doctrine.orm.entity_manager');
    }
}
