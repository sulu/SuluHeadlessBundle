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

use Sulu\Bundle\HeadlessBundle\Content\ContentResolverInterface;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Component\Content\Compat\PropertyParameter;
use Sulu\Component\Content\Compat\StructureInterface;
use Sulu\Component\Content\Compat\StructureManagerInterface;
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
     * @var StructureManagerInterface
     */
    private $structureManager;

    /**
     * @var ContentResolverInterface
     */
    private $contentResolver;

    public function __construct(
        PageDataProvider $pageDataProvider,
        StructureManagerInterface $structureManager,
        ContentResolverInterface $contentResolver
    ) {
        $this->pageDataProvider = $pageDataProvider;
        $this->structureManager = $structureManager;
        $this->contentResolver = $contentResolver;
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
            $properties = $propertyParameter['properties']->getValue();
        }

        $items = [];

        /** @var ArrayAccessItem $providerItem */
        foreach ($providerResult->getItems() as $providerItem) {
            $items[] = $this->resolveProviderItem($providerItem, $properties);
        }

        return new DataProviderResult($items, $providerResult->getHasNextPage());
    }

    /**
     * @param PropertyParameter[] $parameterProperties
     *
     * @return array[]
     */
    private function resolveProviderItem(ArrayAccessItem $providerItem, array $parameterProperties): array
    {
        $data = $providerItem->jsonSerialize();
        $structure = $this->structureManager->getStructure($data['template']);
        $excerpt = $this->structureManager->getStructure('excerpt');

        foreach ($parameterProperties as $parameterProperty) {
            /** @var string $targetPropertyName */
            $targetPropertyName = $parameterProperty->getName();

            /** @var string $propertyName */
            $propertyName = $parameterProperty->getValue();

            $propertyValue = $data[$targetPropertyName] ?? null;
            if (null === $propertyValue) {
                continue;
            }

            $locale = $data['locale'];
            $webspaceKey = $data['webspaceKey'];

            if (false !== strpos($propertyName, '.')) {
                // the '.' is used to separate the extension from the property name.
                $propertyName = explode('.', $propertyName)[1];

                $contentView = $this->resolveProperty($excerpt, $propertyName, $locale, $webspaceKey, $propertyValue);
                $data[$targetPropertyName] = $contentView->getContent();

                continue;
            }

            $contentView = $this->resolveProperty($structure, $propertyName, $locale, $webspaceKey, $propertyValue);
            $data[$targetPropertyName] = $contentView->getContent();
        }

        return $data;
    }

    /**
     * @param mixed $value
     */
    private function resolveProperty(
        StructureInterface $structure,
        string $name,
        string $locale,
        string $webspaceKey,
        $value
    ): ContentView {
        $property = $structure->getProperty($name);
        $property->setValue($value);

        return $this->contentResolver->resolve(
            $value,
            $property,
            $locale,
            ['webspaceKey' => $webspaceKey]
        );
    }
}
