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
use Sulu\Bundle\ContactBundle\Api\Account;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Sulu\Component\Serializer\ArraySerializerInterface;

class AccountSerializer
{
    /**
     * @var ArraySerializerInterface
     */
    private $arraySerializer;

    /**
     * @var MediaSerializer
     */
    private $mediaSerializer;

    /**
     * @var MediaManagerInterface
     */
    private $mediaManager;

    public function __construct(
        ArraySerializerInterface $arraySerializer,
        MediaSerializer $mediaSerializer,
        MediaManagerInterface $mediaManager
    ) {
        $this->arraySerializer = $arraySerializer;
        $this->mediaSerializer = $mediaSerializer;
        $this->mediaManager = $mediaManager;
    }

    /**
     * @return mixed[]
     */
    public function serialize(Account $account, string $locale, ?SerializationContext $context = null): array
    {
        $accountData = $this->arraySerializer->serialize($account, $context);

        unset($accountData['_hash']);

        if (null !== $account->getLogo()) {
            /** @var mixed[] $logoData */
            $logoData = $account->getLogo();
            $logo = $this->mediaManager->getById($logoData['id'], $locale);
            $accountData['logo'] = $this->mediaSerializer->serialize($logo);
        }

        return $accountData;
    }
}
