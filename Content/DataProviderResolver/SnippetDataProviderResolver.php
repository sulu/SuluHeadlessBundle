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

use Sulu\Bundle\HeadlessBundle\Content\StructureResolverInterface;
use Sulu\Component\Content\Compat\PropertyParameter;
use Sulu\Component\Content\Compat\StructureInterface;
use Sulu\Component\Content\Mapper\ContentMapperInterface;
use Sulu\Component\Content\Query\ContentQueryBuilderInterface;
use Sulu\Component\SmartContent\Configuration\ProviderConfigurationInterface;
use Sulu\Component\SmartContent\DataProviderInterface;

class SnippetDataProviderResolver implements DataProviderResolverInterface
{
    public static function getDataProvider(): string
    {
        return 'snippets';
    }

    /**
     * @var DataProviderInterface
     */
    private $snippetDataProvider;

    /**
     * @var StructureResolverInterface
     */
    private $structureResolver;

    /**
     * @var ContentQueryBuilderInterface
     */
    private $contentQueryBuilder;

    /**
     * @var ContentMapperInterface
     */
    private $contentMapper;

    public function __construct(
        DataProviderInterface $snippetDataProvider,
        StructureResolverInterface $structureResolver,
        ContentQueryBuilderInterface $contentQueryBuilder,
        ContentMapperInterface $contentMapper
    ) {
        $this->snippetDataProvider = $snippetDataProvider;
        $this->structureResolver = $structureResolver;
        $this->contentQueryBuilder = $contentQueryBuilder;
        $this->contentMapper = $contentMapper;
    }

    public function getProviderConfiguration(): ProviderConfigurationInterface
    {
        return $this->snippetDataProvider->getConfiguration();
    }

    /**
     * @return PropertyParameter[]
     */
    public function getProviderDefaultParams(): array
    {
        return $this->snippetDataProvider->getDefaultPropertyParameter();
    }

    /**
     * @var PropertyParameter[]
     */
    public function resolve(
        array $filters,
        array $propertyParameters,
        array $options = [],
        ?int $limit = null,
        int $snippet = 1,
        ?int $snippetSize = null
    ): DataProviderResult {
        $providerResult = $this->snippetDataProvider->resolveResourceItems(
            $filters,
            $propertyParameters,
            $options,
            $limit,
            $snippet,
            $snippetSize
        );

        $snippetIds = [];
        foreach ($providerResult->getItems() as $resultItem) {
            $snippetIds[] = $resultItem->getId();
        }

        /** @var PropertyParameter[] $propertiesParamValue */
        $propertiesParamValue = isset($propertyParameters['properties']) ? $propertyParameters['properties']->getValue() : [];

        /** @var string $webspaceKey */
        $webspaceKey = $options['webspaceKey'];
        /** @var string $locale */
        $locale = $options['locale'];

        // the SnippetDataProvider resolves the data defined in the $propertiesParamValue using the default content types
        // for example, this means that the result contains an array of media api entities instead of a raw array of ids
        // to resolve the data with the resolvers of this bundle, we need to load the structures with the ContentMapper
        $snippetStructures = $this->loadSnippetStructures(
            $snippetIds,
            $propertiesParamValue,
            $webspaceKey,
            $locale
        );

        $propertyMap = [
            'title' => 'title',
        ];

        foreach ($propertiesParamValue as $propertiesParamEntry) {
            $paramName = $propertiesParamEntry->getName();
            $paramValue = $propertiesParamEntry->getValue();
            $propertyMap[$paramName] = \is_string($paramValue) ? $paramValue : $paramName;
        }

        $resolvedSnippets = \array_fill_keys($snippetIds, null);

        foreach ($snippetStructures as $snippetStructure) {
            $resolvedSnippets[$snippetStructure->getUuid()] = $this->structureResolver->resolveProperties($snippetStructure, $propertyMap, $locale);
        }

        return new DataProviderResult(\array_values(\array_filter($resolvedSnippets)), $providerResult->getHasNextPage());
    }

    /**
     * @param string[] $snippetIds
     * @param PropertyParameter[] $propertiesParamValue
     *
     * @return StructureInterface[]
     */
    private function loadSnippetStructures(array $snippetIds, array $propertiesParamValue, string $webspaceKey, string $locale): array
    {
        if (0 === \count($snippetIds)) {
            return [];
        }

        $this->contentQueryBuilder->init([
            'ids' => $snippetIds,
            'properties' => $propertiesParamValue,
        ]);
        [$snippetsQuery] = $this->contentQueryBuilder->build($webspaceKey, [$locale]);

        return $this->contentMapper->loadBySql2(
            $snippetsQuery,
            $locale,
            $webspaceKey
        );
    }
}
