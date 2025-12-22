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
use Sulu\Bundle\AdminBundle\SmartContent\Configuration\ProviderConfigurationInterface;
use Sulu\Bundle\AdminBundle\SmartContent\SmartContentProviderInterface;
use Sulu\Bundle\ContactBundle\Entity\ContactRepositoryInterface;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\ContactSerializerInterface;
use Sulu\Component\Content\Compat\PropertyParameter;

class ContactDataProviderResolver implements DataProviderResolverInterface
{
    public static function getDataProvider(): string
    {
        return 'contacts';
    }

    public function __construct(
        private SmartContentProviderInterface $contactSmartContentProvider,
        private ContactSerializerInterface $contactSerializer,
        private ContactRepositoryInterface $contactRepository,
    ) {
    }

    public function getProviderConfiguration(): ProviderConfigurationInterface
    {
        return $this->contactSmartContentProvider->getConfiguration();
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

        $smartFilters = $this->convertFilters($filters, $limit, $page, $pageSize);
        $sortBys = $this->extractSortBys($filters);

        $flatResults = $this->contactSmartContentProvider->findFlatBy($smartFilters, $sortBys, $options);

        $ids = \array_map(fn (array $item) => (int) $item['id'], $flatResults);

        if (empty($ids)) {
            return new DataProviderResult([], false);
        }

        $items = [];
        foreach ($ids as $id) {
            $contact = $this->contactRepository->find($id);
            if (null !== $contact) {
                $items[] = $this->contactSerializer->serialize(
                    $contact,
                    $locale,
                    SerializationContext::create()->setGroups(['partialContact']),
                );
            }
        }

        $hasNextPage = null !== $pageSize && \count($flatResults) >= $pageSize;

        return new DataProviderResult($items, $hasNextPage);
    }

    /**
     * @return array<string, mixed>
     */
    private function convertFilters(array $filters, ?int $limit, int $page, ?int $pageSize): array
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
            'types' => [],
            'typesOperator' => 'OR',
            'locale' => $filters['locale'] ?? 'en',
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
