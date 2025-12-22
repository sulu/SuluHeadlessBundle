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

class SingleContactSelectionResolver implements ContentTypeResolverInterface
{
    public static function getContentType(): string
    {
        return 'single_contact_selection';
    }

    public function __construct(
        private ContactManager $contactManager,
        private ContactSerializerInterface $contactSerializer,
    ) {
    }

    public function resolve(mixed $data, FieldMetadata $fieldMetadata, string $locale, array $attributes = []): ContentView
    {
        if (!\is_numeric($data)) {
            return new ContentView(null, ['id' => null]);
        }

        $contact = $this->contactManager->getById((int) $data, $locale);
        $serializationContext = new SerializationContext();
        $serializationContext->setGroups(['partialContact']);

        $content = $this->contactSerializer->serialize($contact->getEntity(), $locale, $serializationContext);

        return new ContentView($content, ['id' => $data]);
    }
}
