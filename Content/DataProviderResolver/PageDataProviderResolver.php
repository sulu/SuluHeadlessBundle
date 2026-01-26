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

use Sulu\Bundle\AdminBundle\SmartContent\Configuration\ProviderConfigurationInterface;
use Sulu\Bundle\AdminBundle\SmartContent\SmartContentProviderInterface;
use Sulu\Bundle\HeadlessBundle\Content\StructureResolverInterface;
use Sulu\Component\Content\Compat\PropertyParameter;
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;

class PageDataProviderResolver implements DataProviderResolverInterface
{
    public static function getDataProvider(): string
    {
        return 'pages';
    }

    public function __construct(
        private SmartContentProviderInterface $pageSmartContentProvider,
        private StructureResolverInterface $structureResolver,
        private PageRepositoryInterface $pageRepository,
        private ContentAggregatorInterface $contentAggregator,
        private bool $showDrafts,
    ) {
    }

    public function getProviderConfiguration(): ProviderConfigurationInterface
    {
        return $this->pageSmartContentProvider->getConfiguration();
    }

    /**
     * @return PropertyParameter[]
     */
    public function getProviderDefaultParams(): array
    {
        return [];
    }

    public function resolve(
        array $filters,
        array $propertyParameters,
        array $options = [],
        ?int $limit = null,
        int $page = 1,
        ?int $pageSize = null,
    ): DataProviderResult {
        if (!\is_string($options['locale'] ?? null)) {
            throw new \InvalidArgumentException('The "locale" option must be a string.');
        }
        $locale = $options['locale'];
        $webspaceKey = $options['webspaceKey'] ?? null;

        $smartFilters = $this->convertFilters($filters, $limit, $page, $pageSize, $locale);
        $sortBys = $this->extractSortBys($filters);

        $flatResults = $this->pageSmartContentProvider->findFlatBy($smartFilters, $sortBys, $options);

        $ids = \array_map(static fn (array $item) => $item['id'], $flatResults);

        if (empty($ids)) {
            return new DataProviderResult([], false);
        }

        $stage = $this->showDrafts ? 'draft' : 'live';

        $pages = $this->pageRepository->findBy(
            [
                'uuids' => $ids,
                'locale' => $locale,
                'stage' => $stage,
            ],
            [],
            [PageRepositoryInterface::GROUP_SELECT_PAGE_WEBSITE => true],
        );

        /** @var PropertyParameter[] $propertiesParamValue */
        $propertiesParamValue = isset($propertyParameters['properties']) ? $propertyParameters['properties']->getValue() : [];

        $propertyMap = [
            'title' => 'title',
            'url' => 'url',
        ];

        foreach ($propertiesParamValue as $propertiesParamEntry) {
            $paramName = $propertiesParamEntry->getName();
            $paramValue = $propertiesParamEntry->getValue();
            $propertyMap[$paramName] = \is_string($paramValue) ? $paramValue : $paramName;
        }

        $resolvedPages = \array_fill_keys($ids, null);

        foreach ($pages as $pageEntity) {
            $dimensionContent = $this->contentAggregator->aggregate(
                $pageEntity,
                ['locale' => $locale, 'stage' => $stage],
            );
            $resolvedPages[$pageEntity->getUuid()] = $this->structureResolver->resolveProperties(
                $dimensionContent,
                $propertyMap,
                $locale,
            );
        }

        $hasNextPage = null !== $pageSize && \count($flatResults) >= $pageSize;

        return new DataProviderResult(\array_values(\array_filter($resolvedPages)), $hasNextPage);
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<string, mixed>
     */
    private function convertFilters(array $filters, ?int $limit, int $page, ?int $pageSize, string $locale): array
    {
        $offset = 0;
        if (null !== $pageSize && $page > 1) {
            $offset = ($page - 1) * $pageSize;
        }

        return [
            'categories' => $filters['categories'] ?? [],
            'categoryOperator' => $filters['categoryOperator'] ?? 'OR',
            'websiteCategories' => $filters['websiteCategories'] ?? [],
            'websiteCategoryOperator' => $filters['websiteCategoriesOperator'] ?? 'OR',
            'tags' => $filters['tags'] ?? [],
            'tagOperator' => $filters['tagOperator'] ?? 'OR',
            'websiteTags' => $filters['websiteTags'] ?? [],
            'websiteTagOperator' => $filters['websiteTagsOperator'] ?? 'OR',
            'types' => $filters['types'] ?? [],
            'typesOperator' => 'OR',
            'locale' => $locale,
            'dataSource' => $filters['dataSource'] ?? null,
            'limit' => $pageSize ?? $limit,
            'offset' => $offset,
            'includeSubFolders' => $filters['includeSubFolders'] ?? true,
            'excludeDuplicates' => $filters['exclude_duplicates'] ?? false,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<string, string>
     */
    private function extractSortBys(array $filters): array
    {
        $sortBy = $filters['sortBy'] ?? null;
        if (!\is_string($sortBy) || '' === $sortBy) {
            return [];
        }

        $sortMethod = $filters['sortMethod'] ?? 'asc';

        return [$sortBy => \is_string($sortMethod) ? $sortMethod : 'asc'];
    }
}
