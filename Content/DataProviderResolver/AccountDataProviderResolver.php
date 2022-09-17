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
use Sulu\Bundle\ContactBundle\Api\Account;
use Sulu\Bundle\ContactBundle\Entity\Account as AccountEntity;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\AccountSerializerInterface;
use Sulu\Component\Content\Compat\PropertyParameter;
use Sulu\Component\SmartContent\Configuration\ProviderConfigurationInterface;
use Sulu\Component\SmartContent\DataProviderInterface;

class AccountDataProviderResolver implements DataProviderResolverInterface
{
    public static function getDataProvider(): string
    {
        return 'accounts';
    }

    /**
     * @var DataProviderInterface
     */
    private $accountDataProvider;

    /**
     * @var AccountSerializerInterface
     */
    private $accountSerializer;

    public function __construct(
        DataProviderInterface $accountDataProvider,
        AccountSerializerInterface $accountSerializer
    ) {
        $this->accountDataProvider = $accountDataProvider;
        $this->accountSerializer = $accountSerializer;
    }

    public function getProviderConfiguration(): ProviderConfigurationInterface
    {
        return $this->accountDataProvider->getConfiguration();
    }

    /**
     * @return PropertyParameter[]
     */
    public function getProviderDefaultParams(): array
    {
        return $this->accountDataProvider->getDefaultPropertyParameter();
    }

    public function resolve(
        array $filters,
        array $propertyParameters,
        array $options = [],
        ?int $limit = null,
        int $page = 1,
        ?int $pageSize = null
    ): DataProviderResult {
        $providerResult = $this->accountDataProvider->resolveResourceItems(
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
            /** @var Account $account */
            $account = $providerItem->getResource();
            /** @var AccountEntity $accountEntity */
            $accountEntity = $account->getEntity();

            $items[] = $this->accountSerializer->serialize(
                $accountEntity,
                $locale,
                SerializationContext::create()->setGroups(['partialAccount'])
            );
        }

        return new DataProviderResult($items, $providerResult->getHasNextPage());
    }
}
