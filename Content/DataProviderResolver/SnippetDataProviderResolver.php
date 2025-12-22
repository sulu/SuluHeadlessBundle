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
use Sulu\Content\Application\ContentMerger\ContentMergerInterface;
use Sulu\Content\Domain\Model\DimensionContentCollection;
use Sulu\Snippet\Domain\Model\SnippetDimensionContent;
use Sulu\Snippet\Domain\Repository\SnippetRepositoryInterface;

class SnippetDataProviderResolver implements DataProviderResolverInterface
{
    public static function getDataProvider(): string
    {
        return 'snippets';
    }

    public function __construct(
        private SmartContentProviderInterface $snippetSmartContentProvider,
        private StructureResolverInterface $structureResolver,
        private SnippetRepositoryInterface $snippetRepository,
        private ContentMergerInterface $contentMerger,
    ) {
    }

    public function getProviderConfiguration(): ProviderConfigurationInterface
    {
        return $this->snippetSmartContentProvider->getConfiguration();
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
        $locale = $options['locale'] ?? 'en';

        $smartFilters = $this->convertFilters($filters, $limit, $page, $pageSize, $locale);
        $sortBys = $this->extractSortBys($filters);

        $flatResults = $this->snippetSmartContentProvider->findFlatBy($smartFilters, $sortBys, $options);

        $ids = \array_map(fn (array $item) => $item['id'], $flatResults);

        if (empty($ids)) {
            return new DataProviderResult([], false);
        }

        $snippets = $this->snippetRepository->findBy(
            [
                'uuids' => $ids,
                'locale' => $locale,
                'stage' => 'live',
                'load_ghost_content' => true,
            ],
            [],
            [SnippetRepositoryInterface::GROUP_SELECT_SNIPPET_WEBSITE => true],
        );

        /** @var PropertyParameter[] $propertiesParamValue */
        $propertiesParamValue = isset($propertyParameters['properties']) ? $propertyParameters['properties']->getValue() : [];

        $propertyMap = [
            'title' => 'title',
        ];

        foreach ($propertiesParamValue as $propertiesParamEntry) {
            $paramName = $propertiesParamEntry->getName();
            $paramValue = $propertiesParamEntry->getValue();
            $propertyMap[$paramName] = \is_string($paramValue) ? $paramValue : $paramName;
        }

        $resolvedSnippets = \array_fill_keys($ids, null);

        foreach ($snippets as $snippetEntity) {
            $dimensionContentCollection = new DimensionContentCollection(
                $snippetEntity->getDimensionContents(),
                ['locale' => $locale, 'stage' => 'live'],
                SnippetDimensionContent::class,
            );

            $dimensionContent = $this->contentMerger->merge($dimensionContentCollection);
            $resolvedSnippets[$snippetEntity->getUuid()] = $this->structureResolver->resolveProperties(
                $dimensionContent,
                $propertyMap,
                $locale,
            );
        }

        $hasNextPage = null !== $pageSize && \count($flatResults) >= $pageSize;

        return new DataProviderResult(\array_values(\array_filter($resolvedSnippets)), $hasNextPage);
    }

    /**
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
            'includeSubFolders' => true,
            'excludeDuplicates' => $filters['exclude_duplicates'] ?? false,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function extractSortBys(array $filters): array
    {
        if (!isset($filters['sortBy']) || empty($filters['sortBy'])) {
            return [];
        }

        return [$filters['sortBy'] => $filters['sortMethod'] ?? 'asc'];
    }
}
