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
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\AccountSerializerInterface;

class AccountSelectionResolver implements ContentTypeResolverInterface
{
    public static function getContentType(): string
    {
        return 'account_selection';
    }

    public function __construct(
        private AccountManager $accountManager,
        private AccountSerializerInterface $accountSerializer,
    ) {
    }

    public function resolve(mixed $data, FieldMetadata $fieldMetadata, string $locale, array $attributes = []): ContentView
    {
        if (empty($data) || !\is_array($data)) {
            return new ContentView([], ['ids' => []]);
        }

        $content = [];
        foreach ($this->accountManager->getByIds($data, $locale) as $account) {
            $serializationContext = new SerializationContext();
            $serializationContext->setGroups(['partialAccount']);

            $content[] = $this->accountSerializer->serialize($account->getEntity(), $locale, $serializationContext);
        }

        return new ContentView($content, ['ids' => $data]);
    }
}
