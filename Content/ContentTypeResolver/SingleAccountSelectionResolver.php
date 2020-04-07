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
use Sulu\Bundle\ContactBundle\Contact\AccountManager;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\AccountSerializerInterface;
use Sulu\Component\Content\Compat\PropertyInterface;

class SingleAccountSelectionResolver implements ContentTypeResolverInterface
{
    public static function getContentType(): string
    {
        return 'single_account_selection';
    }

    /**
     * @var AccountManager
     */
    private $accountManager;

    /**
     * @var AccountSerializerInterface
     */
    private $accountSerializer;

    public function __construct(
        AccountManager $accountManager,
        AccountSerializerInterface $accountSerializer
    ) {
        $this->accountManager = $accountManager;
        $this->accountSerializer = $accountSerializer;
    }

    public function resolve($data, PropertyInterface $property, string $locale, array $attributes = []): ContentView
    {
        if (null === $data) {
            return new ContentView([]);
        }

        $account = $this->accountManager->getById((int) $data, $locale);
        $serializationContext = new SerializationContext();
        $serializationContext->setGroups(['partialAccount']);

        $content = $this->accountSerializer->serialize($account, $locale, $serializationContext);

        return new ContentView($content, [$data]);
    }
}
