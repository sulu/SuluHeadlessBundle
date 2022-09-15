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

namespace Sulu\Bundle\HeadlessBundle\Content\DataProviderResolver;

use JMS\Serializer\SerializationContext;
use Sulu\Bundle\ContactBundle\Api\Contact;
use Sulu\Bundle\ContactBundle\Entity\Contact as ContactEntity;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\ContactSerializerInterface;
use Sulu\Component\Content\Compat\PropertyParameter;
use Sulu\Component\SmartContent\Configuration\ProviderConfigurationInterface;
use Sulu\Component\SmartContent\DataProviderInterface;

class ContactDataProviderResolver implements DataProviderResolverInterface
{
    public static function getDataProvider(): string
    {
        return 'contacts';
    }

    /**
     * @var DataProviderInterface
     */
    private $contactDataProvider;

    /**
     * @var ContactSerializerInterface
     */
    private $contactSerializer;

    public function __construct(
        DataProviderInterface $contactDataProvider,
        ContactSerializerInterface $contactSerializer
    ) {
        $this->contactDataProvider = $contactDataProvider;
        $this->contactSerializer = $contactSerializer;
    }

    public function getProviderConfiguration(): ProviderConfigurationInterface
    {
        return $this->contactDataProvider->getConfiguration();
    }

    /**
     * @return PropertyParameter[]
     */
    public function getProviderDefaultParams(): array
    {
        return $this->contactDataProvider->getDefaultPropertyParameter();
    }

    public function resolve(
        array $filters,
        array $propertyParameters,
        array $options = [],
        ?int $limit = null,
        int $page = 1,
        ?int $pageSize = null
    ): DataProviderResult {
        $providerResult = $this->contactDataProvider->resolveResourceItems(
            $filters,
            $propertyParameters,
            $options,
            $limit,
            $page,
            $pageSize
        );

        /** @var string $locale */
        $locale = $options['locale'];

        $items = [];
        foreach ($providerResult->getItems() as $providerItem) {
            /** @var Contact $contact */
            $contact = $providerItem->getResource();
            /** @var ContactEntity $contactEntity */
            $contactEntity = $contact->getEntity();

            $items[] = $this->contactSerializer->serialize(
                $contactEntity,
                $locale,
                SerializationContext::create()->setGroups(['partialContact'])
            );
        }

        return new DataProviderResult($items, $providerResult->getHasNextPage());
    }
}
