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
use Sulu\Bundle\ContactBundle\Entity\AccountRepositoryInterface;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\AccountSerializerInterface;
use Sulu\Component\Content\Compat\PropertyParameter;

class AccountDataProviderResolver implements DataProviderResolverInterface
{
    public static function getDataProvider(): string
    {
        return 'accounts';
    }

    public function __construct(
        private SmartContentProviderInterface $accountSmartContentProvider,
        private AccountSerializerInterface $accountSerializer,
        private AccountRepositoryInterface $accountRepository,
    ) {
    }

    public function getProviderConfiguration(): ProviderConfigurationInterface
    {
        return $this->accountSmartContentProvider->getConfiguration();
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

        $smartFilters = $this->convertFilters($filters, $locale, $limit, $page, $pageSize);
        $sortBys = $this->extractSortBys($filters);

        $flatResults = $this->accountSmartContentProvider->findFlatBy($smartFilters, $sortBys, $options);

        $ids = \array_map(static fn (array $item) => (int) $item['id'], $flatResults);

        if (empty($ids)) {
            return new DataProviderResult([], false);
        }

        $accounts = $this->accountRepository->findByIds($ids);

        $items = \array_fill_keys($ids, null);
        foreach ($accounts as $account) {
            $items[$account->getId()] = $this->accountSerializer->serialize(
                $account,
                $locale,
                SerializationContext::create()->setGroups(['partialAccount']),
            );
        }
        $items = \array_values(\array_filter($items));

        $hasNextPage = null !== $pageSize && \count($flatResults) >= $pageSize;

        return new DataProviderResult($items, $hasNextPage);
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<string, mixed>
     */
    private function convertFilters(array $filters, string $locale, ?int $limit, int $page, ?int $pageSize): array
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
            'locale' => $locale,
            'dataSource' => $filters['dataSource'] ?? null,
            'limit' => $pageSize ?? $limit,
            'offset' => $offset,
            'includeSubFolders' => true,
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
