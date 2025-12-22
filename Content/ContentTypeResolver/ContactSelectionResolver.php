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
use Sulu\Bundle\ContactBundle\Contact\ContactManager;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\ContactSerializerInterface;

class ContactSelectionResolver implements ContentTypeResolverInterface
{
    public static function getContentType(): string
    {
        return 'contact_selection';
    }

    public function __construct(
        private ContactManager $contactManager,
        private ContactSerializerInterface $contactSerializer,
    ) {
    }

    public function resolve(mixed $data, FieldMetadata $fieldMetadata, string $locale, array $attributes = []): ContentView
    {
        if (empty($data) || !\is_array($data)) {
            return new ContentView([], ['ids' => []]);
        }

        $content = [];
        foreach ($this->contactManager->getByIds($data, $locale) as $contact) {
            $serializationContext = new SerializationContext();
            $serializationContext->setGroups(['partialContact']);

            $content[] = $this->contactSerializer->serialize($contact->getEntity(), $locale, $serializationContext);
        }

        return new ContentView($content, ['ids' => $data]);
    }
}
