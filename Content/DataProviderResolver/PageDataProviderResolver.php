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
use Sulu\Component\Content\SmartContent\PageDataProvider;
use Sulu\Component\SmartContent\Configuration\ProviderConfigurationInterface;

class PageDataProviderResolver implements DataProviderResolverInterface
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

    /**
     * @var bool
     */
    private $showDrafts;

    public function __construct(
        PageDataProvider $pageDataProvider,
        StructureResolverInterface $structureResolver,
        ContentQueryBuilderInterface $contentQueryBuilder,
        ContentMapperInterface $contentMapper,
        bool $showDrafts
    ) {
        $this->pageDataProvider = $pageDataProvider;
        $this->structureResolver = $structureResolver;
        $this->contentQueryBuilder = $contentQueryBuilder;
        $this->contentMapper = $contentMapper;
        $this->showDrafts = $showDrafts;
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
        array $propertyParameters,
        array $options = [],
        ?int $limit = null,
        int $page = 1,
        ?int $pageSize = null
    ): DataProviderResult {
        $providerResult = $this->pageDataProvider->resolveResourceItems(
            $filters,
            $propertyParameters,
            $options,
            $limit,
            $page,
            $pageSize
        );

        $pageIds = [];
        foreach ($providerResult->getItems() as $resultItem) {
            $pageIds[] = $resultItem->getId();
        }

        /** @var PropertyParameter[] $propertiesParamValue */
        $propertiesParamValue = isset($propertyParameters['properties']) ? $propertyParameters['properties']->getValue() : [];

        // the PageDataProvider resolves the data defined in the $propertiesParamValue using the default content types
        // for example, this means that the result contains an array of media api entities instead of a raw array of ids
        // to resolve the data with the resolvers of this bundle, we need to load the structures with the ContentMapper
        $pageStructures = $this->loadPageStructures(
            $pageIds,
            $propertiesParamValue,
            $options['webspaceKey'],
            $options['locale']
        );

        $propertyMap = [
            'title' => 'title',
            'url' => 'url',
        ];

        foreach ($propertiesParamValue as $propertiesParamEntry) {
            $paramName = $propertiesParamEntry->getName();
            $paramValue = $propertiesParamEntry->getValue();
            $propertyMap[$paramName] = \is_string($paramValue) ? $paramValue : $paramName;
        }

        $resolvedPages = [];
        foreach ($pageStructures as $pageStructure) {
            $resolvedPages[] = $this->structureResolver->resolveProperties($pageStructure, $propertyMap, $options['locale']);
        }

        return new DataProviderResult($resolvedPages, $providerResult->getHasNextPage());
    }

    /**
     * @param string[] $pageIds
     * @param PropertyParameter[] $propertiesParamValue
     *
     * @return StructureInterface[]
     */
    private function loadPageStructures(array $pageIds, array $propertiesParamValue, string $webspaceKey, string $locale): array
    {
        if (0 === \count($pageIds)) {
            return [];
        }

        $this->contentQueryBuilder->init([
            'ids' => $pageIds,
            'properties' => $propertiesParamValue,
            'published' => !$this->showDrafts,
        ]);
        list($pagesQuery) = $this->contentQueryBuilder->build($webspaceKey, [$locale]);

        return $this->contentMapper->loadBySql2(
            $pagesQuery,
            $locale,
            $webspaceKey
        );
    }
}
