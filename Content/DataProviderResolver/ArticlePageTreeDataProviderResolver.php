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

use Sulu\Article\Domain\Repository\ArticleRepositoryInterface;
use Sulu\Bundle\AdminBundle\SmartContent\Configuration\ProviderConfigurationInterface;
use Sulu\Bundle\AdminBundle\SmartContent\SmartContentProviderInterface;
use Sulu\Bundle\HeadlessBundle\Content\StructureResolverInterface;
use Sulu\Component\Content\Compat\PropertyParameter;
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;

class ArticlePageTreeDataProviderResolver implements DataProviderResolverInterface
{
    public static function getDataProvider(): string
    {
        return 'articles_page_tree';
    }

    public function __construct(
        private SmartContentProviderInterface $articlePageTreeSmartContentProvider,
        private StructureResolverInterface $structureResolver,
        private ArticleRepositoryInterface $articleRepository,
        private ContentAggregatorInterface $contentAggregator,
        private bool $showDrafts,
    ) {
    }

    public function getProviderConfiguration(): ProviderConfigurationInterface
    {
        return $this->articlePageTreeSmartContentProvider->getConfiguration();
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

        $smartFilters = $this->convertFilters($filters, $limit, $page, $pageSize, $locale);
        $sortBys = $this->extractSortBys($filters);

        $flatResults = $this->articlePageTreeSmartContentProvider->findFlatBy($smartFilters, $sortBys, $options);

        $ids = \array_map(static fn (array $item) => $item['id'], $flatResults);

        if (empty($ids)) {
            return new DataProviderResult([], false);
        }

        $stage = $this->showDrafts ? 'draft' : 'live';

        $articles = $this->articleRepository->findBy(
            [
                'uuids' => $ids,
                'locale' => $locale,
                'stage' => $stage,
            ],
            [],
            [ArticleRepositoryInterface::GROUP_SELECT_ARTICLE_WEBSITE => true],
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

        $resolvedArticles = \array_fill_keys($ids, null);

        foreach ($articles as $articleEntity) {
            $dimensionContent = $this->contentAggregator->aggregate(
                $articleEntity,
                ['locale' => $locale, 'stage' => $stage],
            );
            $resolvedArticles[$articleEntity->getUuid()] = $this->structureResolver->resolveProperties(
                $dimensionContent,
                $propertyMap,
                $locale,
            );
        }

        $hasNextPage = null !== $pageSize && \count($flatResults) >= $pageSize;

        return new DataProviderResult(\array_values(\array_filter($resolvedArticles)), $hasNextPage);
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
