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

use Sulu\Bundle\ContactBundle\Entity\Contact;
use Sulu\Bundle\ContactBundle\Entity\ContactInterface;

trait CreateContactTrait
{
    /**
     * @param array{firstName: string, lastName: string, formOfAddress?: int} $data
     */
    private static function createContact(array $data): ContactInterface
    {
        $manager = static::getContainer()->get('doctrine.orm.entity_manager');

        $contact = new Contact();
        $contact->setFirstName($data['firstName']);
        $contact->setLastName($data['lastName']);

        if (isset($data['formOfAddress'])) {
            $contact->setFormOfAddress($data['formOfAddress']);
        }

        $manager->persist($contact);

        return $contact;
    }
}
