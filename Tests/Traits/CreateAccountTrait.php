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

namespace Sulu\Bundle\HeadlessBundle\Tests\Traits;

use Sulu\Bundle\ContactBundle\Entity\Account;
use Sulu\Bundle\ContactBundle\Entity\AccountInterface;

trait CreateAccountTrait
{
    /**
     * @param array{name: string, corporation?: string, number?: string} $data
     */
    private static function createAccount(array $data): AccountInterface
    {
        $manager = static::getContainer()->get('doctrine.orm.entity_manager');

        $account = new Account();
        $account->setName($data['name']);

        if (isset($data['corporation'])) {
            $account->setCorporation($data['corporation']);
        }

        if (isset($data['number'])) {
            $account->setNumber($data['number']);
        }

        $manager->persist($account);

        return $account;
    }
}
