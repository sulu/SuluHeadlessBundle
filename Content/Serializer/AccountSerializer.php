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
use Sulu\Bundle\ContactBundle\Contact\AccountManager;
use Sulu\Bundle\ContactBundle\Entity\Account as EntityAccount;
use Sulu\Bundle\ContactBundle\Entity\AccountInterface;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Sulu\Component\Serializer\ArraySerializerInterface;

class AccountSerializer implements AccountSerializerInterface
{
    /**
     * @var AccountManager
     */
    private $accountManager;

    /**
     * @var ArraySerializerInterface
     */
    private $arraySerializer;

    /**
     * @var MediaSerializerInterface
     */
    private $mediaSerializer;

    /**
     * @var MediaManagerInterface
     */
    private $mediaManager;

    public function __construct(
        AccountManager $accountManager,
        ArraySerializerInterface $arraySerializer,
        MediaSerializerInterface $mediaSerializer,
        MediaManagerInterface $mediaManager
    ) {
        $this->accountManager = $accountManager;
        $this->arraySerializer = $arraySerializer;
        $this->mediaSerializer = $mediaSerializer;
        $this->mediaManager = $mediaManager;
    }

    /**
     * @param EntityAccount $account
     *
     * @return mixed[]
     */
    public function serialize(AccountInterface $account, string $locale, ?SerializationContext $context = null): array
    {
        /** @var Account $apiAccount */
        $apiAccount = $this->accountManager->getAccount($account, $locale);
        $accountData = $this->arraySerializer->serialize($apiAccount, $context);

        unset($accountData['_hash']);

        $note = $apiAccount->getNote();
        if ($note) {
            $accountData['note'] = $note;
        }

        if (null !== $apiAccount->getLogo()) {
            /** @var mixed[] $logoData */
            $logoData = $apiAccount->getLogo();
            $logo = $this->mediaManager->getById($logoData['id'], $locale);
            $accountData['logo'] = $this->mediaSerializer->serialize($logo->getEntity(), $locale);
        }

        return $accountData;
    }
}
