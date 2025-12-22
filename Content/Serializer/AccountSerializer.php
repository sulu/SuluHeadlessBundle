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
use Sulu\Bundle\HttpCacheBundle\ReferenceStore\ReferenceStoreInterface;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Sulu\Component\Serializer\ArraySerializerInterface;

class AccountSerializer implements AccountSerializerInterface
{
    public function __construct(
        private AccountManager $accountManager,
        private ArraySerializerInterface $arraySerializer,
        private MediaSerializerInterface $mediaSerializer,
        private MediaManagerInterface $mediaManager,
        private ReferenceStoreInterface $referenceStore,
    ) {
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
            /** @var array{id: int, url: string, thumbnails: array<string, string>} $logoData */
            $logoData = $apiAccount->getLogo();
            $logo = $this->mediaManager->getById($logoData['id'], $locale);
            $accountData['logo'] = $this->mediaSerializer->serialize($logo->getEntity(), $locale);
        }

        $this->referenceStore->add((string) $account->getId(), 'account');

        return $accountData;
    }
}
