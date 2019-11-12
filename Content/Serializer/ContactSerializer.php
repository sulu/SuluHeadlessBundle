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
use Sulu\Bundle\ContactBundle\Entity\ContactTitleRepository;
use Sulu\Bundle\ContactBundle\Entity\PositionRepository;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Sulu\Component\Serializer\ArraySerializerInterface;

class ContactSerializer
{
    /**
     * @var ArraySerializerInterface
     */
    private $arraySerializer;

    /**
     * @var MediaManagerInterface
     */
    private $mediaManager;

    /**
     * @var MediaSerializer
     */
    private $mediaSerializer;

    /**
     * @var ContactTitleRepository
     */
    private $contactTitleRepository;

    /**
     * @var PositionRepository
     */
    private $positionRepository;

    public function __construct(
        ArraySerializerInterface $arraySerializer,
        MediaManagerInterface $mediaManager,
        MediaSerializer $mediaSerializer,
        ContactTitleRepository $contactTitleRepository,
        PositionRepository $positionRepository
    ) {
        $this->arraySerializer = $arraySerializer;
        $this->mediaManager = $mediaManager;
        $this->mediaSerializer = $mediaSerializer;
        $this->contactTitleRepository = $contactTitleRepository;
        $this->positionRepository = $positionRepository;
    }

    /**
     * @return mixed[]
     */
    public function serialize(Contact $contact, string $locale, ?SerializationContext $context = null): array
    {
        $contactData = $this->arraySerializer->serialize($contact, $context);

        unset($contactData['_hash']);

        if (isset($contactData['title'])) {
            $contactData['title'] = $this->contactTitleRepository->find($contactData['title'])->getTitle();
        }

        if (isset($contactData['position'])) {
            $contactData['position'] = $this->positionRepository->find($contactData['position'])->getPosition();
        }

        if (null !== $contact->getAvatar()) {
            /** @var mixed[] $avatarData */
            $avatarData = $contact->getAvatar();
            $avatar = $this->mediaManager->getById($avatarData['id'], $locale);
            $contactData['avatar'] = $this->mediaSerializer->serialize($avatar);
        }

        return $contactData;
    }
}
