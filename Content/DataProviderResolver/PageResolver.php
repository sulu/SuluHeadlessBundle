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

use Sulu\Bundle\HeadlessBundle\Content\Serializer\PageSerializerInterface;
use Sulu\Component\Content\Compat\PropertyParameter;
use Sulu\Component\Content\SmartContent\PageDataProvider;
use Sulu\Component\SmartContent\ArrayAccessItem;
use Sulu\Component\SmartContent\Configuration\ProviderConfigurationInterface;

class PageResolver implements DataProviderResolverInterface
{
    public static function getDataProvider(): string
    {
        return 'pages';
    }

    /**
     * @var PageDataProvider
     */
    private $pageDataProvider;

    /**
     * @var PageSerializerInterface
     */
    private $pageSerializer;

    public function __construct(
        PageDataProvider $pageDataProvider,
        PageSerializerInterface $pageSerializer
    ) {
        $this->pageDataProvider = $pageDataProvider;
        $this->pageSerializer = $pageSerializer;
    }

    public function getProviderConfiguration(): ProviderConfigurationInterface
    {
        return $this->pageDataProvider->getConfiguration();
    }

    /**
     * @return PropertyParameter[]
     */
    public function getProviderDefaultParams(): array
    {
        return $this->pageDataProvider->getDefaultPropertyParameter();
    }

    /**
     * @var PropertyParameter[]
     */
    public function resolve(
        array $filters,
        array $propertyParameter,
        array $options = [],
        ?int $limit = null,
        int $page = 1,
        ?int $pageSize = null
    ): DataProviderResult {
        $providerResult = $this->pageDataProvider->resolveResourceItems(
            $filters,
            $propertyParameter,
            $options,
            $limit,
            $page,
            $pageSize
        );

        $properties = [];
        if (\array_key_exists('properties', $propertyParameter)) {
            /** @var PropertyParameter[] $properties */
            $properties = $propertyParameter['properties']->getValue();
        }

        $items = [];

        /** @var ArrayAccessItem $providerItem */
        foreach ($providerResult->getItems() as $providerItem) {
            $items[] = $this->pageSerializer->serialize($providerItem->jsonSerialize(), $properties);
        }

        return new DataProviderResult($items, $providerResult->getHasNextPage());
    }
}
