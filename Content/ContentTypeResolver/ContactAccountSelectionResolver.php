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

namespace Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver;

use JMS\Serializer\SerializationContext;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\ContactBundle\Contact\AccountManager;
use Sulu\Bundle\ContactBundle\Contact\ContactManager;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\AccountSerializerInterface;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\ContactSerializerInterface;

class ContactAccountSelectionResolver implements ContentTypeResolverInterface
{
    public static function getContentType(): string
    {
        return 'contact_account_selection';
    }

    public function __construct(
        private ContactManager $contactManager,
        private AccountManager $accountManager,
        private ContactSerializerInterface $contactSerializer,
        private AccountSerializerInterface $accountSerializer,
    ) {
    }

    public function resolve(mixed $data, FieldMetadata $fieldMetadata, string $locale, array $attributes = []): ContentView
    {
        if (empty($data) || !\is_array($data)) {
            return new ContentView([], ['ids' => []]);
        }

        $content = [];
        foreach ($data as $entry) {
            $serializationContext = new SerializationContext();
            if (0 === \strncmp($entry, 'c', 1)) {
                $contact = $this->contactManager->getById((int) \substr($entry, 1), $locale);
                $serializationContext->setGroups(['partialContact']);

                $content[] = $this->contactSerializer->serialize($contact->getEntity(), $locale, $serializationContext);
            } elseif (0 === \strncmp($entry, 'a', 1)) {
                $account = $this->accountManager->getById((int) \substr($entry, 1), $locale);
                $serializationContext->setGroups(['partialAccount']);

                $content[] = $this->accountSerializer->serialize($account->getEntity(), $locale, $serializationContext);
            }
        }

        return new ContentView($content, ['ids' => $data]);
    }
}
