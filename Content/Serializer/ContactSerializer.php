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

namespace Sulu\Bundle\HeadlessBundle\Content\Serializer;

use JMS\Serializer\SerializationContext;
use Sulu\Bundle\ContactBundle\Api\Contact;
use Sulu\Bundle\ContactBundle\Contact\ContactManager;
use Sulu\Bundle\ContactBundle\Entity\ContactInterface;
use Sulu\Bundle\ContactBundle\Entity\ContactTitle;
use Sulu\Bundle\ContactBundle\Entity\ContactTitleRepository;
use Sulu\Bundle\ContactBundle\Entity\Position;
use Sulu\Bundle\ContactBundle\Entity\PositionRepository;
use Sulu\Bundle\HttpCacheBundle\ReferenceStore\ReferenceStoreInterface;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Sulu\Component\Serializer\ArraySerializerInterface;

class ContactSerializer implements ContactSerializerInterface
{
    public function __construct(
        private ContactManager $contactManager,
        private ArraySerializerInterface $arraySerializer,
        private MediaManagerInterface $mediaManager,
        private MediaSerializerInterface $mediaSerializer,
        private ContactTitleRepository $contactTitleRepository,
        private PositionRepository $positionRepository,
        private ReferenceStoreInterface $referenceStore,
    ) {
    }

    /**
     * @return mixed[]
     */
    public function serialize(ContactInterface $contact, string $locale, ?SerializationContext $context = null): array
    {
        /** @var Contact $apiContact */
        $apiContact = $this->contactManager->getContact($contact, $locale);
        $contactData = $this->arraySerializer->serialize($apiContact, $context);

        unset($contactData['_hash']);

        $note = $apiContact->getNote();
        if ($note) {
            $contactData['note'] = $note;
        }

        if (isset($contactData['title'])) {
            /** @var ContactTitle|null $titleEntity */
            $titleEntity = $this->contactTitleRepository->find($contactData['title']);
            if ($titleEntity) {
                $contactData['title'] = $titleEntity->getTitle();
            }
        }

        if (isset($contactData['position'])) {
            /** @var Position|null $contactPosition */
            $contactPosition = $this->positionRepository->find($contactData['position']);
            if ($contactPosition) {
                $contactData['position'] = $contactPosition->getPosition();
            }
        }

        if (null !== $apiContact->getAvatar()) {
            /** @var array{id: int, url: string, thumbnails: array<string, string>} $avatarData */
            $avatarData = $apiContact->getAvatar();
            $avatar = $this->mediaManager->getById($avatarData['id'], $locale);
            $contactData['avatar'] = $this->mediaSerializer->serialize($avatar->getEntity(), $locale);
        }

        $this->referenceStore->add((string) $contact->getId(), 'contact');

        return $contactData;
    }
}
