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

namespace Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver;

use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\OptionMetadata;
use Sulu\Bundle\AudienceTargetingBundle\TargetGroup\TargetGroupStoreInterface;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\DataProviderResolver\DataProviderResolverInterface;
use Sulu\Bundle\TagBundle\Tag\TagManagerInterface;
use Sulu\Component\Category\Request\CategoryRequestHandlerInterface;
use Sulu\Component\Content\Compat\PropertyParameter;
use Sulu\Component\Tag\Request\TagRequestHandlerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class SmartContentResolver implements ContentTypeResolverInterface
{
    public static function getContentType(): string
    {
        return 'smart_content';
    }

    /**
     * @var DataProviderResolverInterface[]|null
     */
    private ?array $resolversArray = null;

    /**
     * @param \Traversable<DataProviderResolverInterface> $resolvers
     */
    public function __construct(
        private \Traversable $resolvers,
        private TagManagerInterface $tagManager,
        private RequestStack $requestStack,
        private TagRequestHandlerInterface $tagRequestHandler,
        private CategoryRequestHandlerInterface $categoryRequestHandler,
        private ?TargetGroupStoreInterface $targetGroupStore = null,
    ) {
    }

    /**
     * @return DataProviderResolverInterface[]
     */
    private function getResolvers(): array
    {
        if (null === $this->resolversArray) {
            $this->resolversArray = \iterator_to_array($this->resolvers);
        }

        return $this->resolversArray;
    }

    public function resolve(mixed $data, FieldMetadata $fieldMetadata, string $locale, array $attributes = []): ContentView
    {
        // gather data provider and effective parameters
        $providerResolver = $this->getProviderResolver($fieldMetadata);

        if (null === $providerResolver) {
            return new ContentView(null, \is_array($data) ? $data : []);
        }

        /** @var PropertyParameter[] $params */
        $params = \array_merge(
            $this->getDefaultParams($providerResolver),
            $this->convertOptionsToParams($fieldMetadata->getOptions())
        );

        $filters = \is_array($data) ? $data : [];
        $filters['excluded'] = isset($attributes['uuid']) ? [$attributes['uuid']] : [];

        // default value for the tag/category filter is an empty array
        if (!\array_key_exists('tags', $filters) || null === $filters['tags']) {
            $filters['tags'] = [];
        }
        if (!\array_key_exists('categories', $filters) || null === $filters['categories']) {
            $filters['categories'] = [];
        }

        // add tags that are set via website query parameter to the filters array
        /** @var string $tagsParameter */
        $tagsParameter = $params['tags_parameter']->getValue();
        $filters['websiteTags'] = $this->tagRequestHandler->getTags($tagsParameter);
        $filters['websiteTagsOperator'] = $params['website_tags_operator']->getValue();

        // add categories that are set via website query parameter to the filters array
        /** @var string $categoriesParameter */
        $categoriesParameter = $params['categories_parameter']->getValue();
        $filters['websiteCategories'] = $this->categoryRequestHandler->getCategories($categoriesParameter);
        $filters['websiteCategoriesOperator'] = $params['website_categories_operator']->getValue();

        // add target group to filters array if audience targeting is enabled
        if ($this->targetGroupStore && isset($filters['audienceTargeting']) && $filters['audienceTargeting']) {
            $filters['targetGroupId'] = $this->targetGroupStore->getTargetGroupId();
        }

        // resolve tag names to ids
        $filters['tags'] = $this->resolveTagIds($filters['tags']);
        $filters['websiteTags'] = $this->resolveTagIds($filters['websiteTags']);

        // prepare pagination, limits and options
        $configuration = $providerResolver->getProviderConfiguration();
        $page = 1;
        $limit = $configuration->hasLimit() ? $filters['limitResult'] ?? null : null;
        $options = [
            'webspaceKey' => $attributes['webspaceKey'] ?? null,
            'locale' => $locale,
        ];

        if (isset($params['groups']) && $params['groups']->getValue()) {
            $options['groups'] = $params['groups']->getValue();
        }

        if (isset($params['max_per_page']) && $configuration->hasPagination()) {
            /** @var string $pageParameter */
            $pageParameter = $params['page_parameter']->getValue();
            $page = $this->getCurrentPage($pageParameter);

            /** @var string $pageSize */
            $pageSize = $params['max_per_page']->getValue();

            $result = $providerResolver->resolve(
                $filters,
                $params,
                $options,
                $limit ? (int) $limit : null,
                $page,
                (int) $pageSize
            );
        } else {
            $result = $providerResolver->resolve(
                $filters,
                $params,
                $options,
                $limit ? (int) $limit : null
            );
        }

        $viewData = $filters;
        $viewData['page'] = $page;
        $viewData['hasNextPage'] = $result->getHasNextPage();
        $viewData['paginated'] = $configuration->hasPagination();

        return new ContentView($result->getItems(), $viewData);
    }

    private function getProviderResolver(FieldMetadata $fieldMetadata): ?DataProviderResolverInterface
    {
        $options = $fieldMetadata->getOptions();

        $providerAlias = 'pages';
        foreach ($options as $option) {
            if ('provider' === $option->getName()) {
                $value = $option->getValue();
                if (\is_string($value) || \is_int($value)) {
                    $providerAlias = (string) $value;
                }
                break;
            }
        }

        return $this->getResolvers()[$providerAlias] ?? null;
    }

    /**
     * @param array<OptionMetadata> $options
     *
     * @return array<string, PropertyParameter>
     */
    private function convertOptionsToParams(array $options): array
    {
        $params = [];
        foreach ($options as $option) {
            $name = $option->getName();
            if (!\is_string($name)) {
                continue;
            }

            $value = $option->getValue();
            if (\is_int($value)) {
                $params[$name] = new PropertyParameter($name, (string) $value);
            } elseif (\is_array($value) || \is_bool($value) || \is_string($value)) {
                $params[$name] = new PropertyParameter($name, $value);
            }
        }

        return $params;
    }

    /**
     * @param int[]|string[] $tagIdentifiers
     *
     * @return int[]
     */
    private function resolveTagIds(?array $tagIdentifiers): array
    {
        if (!$tagIdentifiers) {
            return [];
        }

        $ids = [];

        foreach ($tagIdentifiers as $tagIdentifier) {
            if (\is_numeric($tagIdentifier)) {
                $ids[] = (int) $tagIdentifier;
            } else {
                $tag = $this->tagManager->findByName($tagIdentifier);
                if (null !== $tag) {
                    $ids[] = $tag->getId();
                }
            }
        }

        return $ids;
    }

    private function getCurrentPage(string $pageParameter): int
    {
        if (null === $this->requestStack->getCurrentRequest()) {
            return 1;
        }

        $page = (int) $this->requestStack->getCurrentRequest()->get($pageParameter, 1);

        if ($page <= 1) {
            $page = 1;
        }

        if ($page > \PHP_INT_MAX) {
            return \PHP_INT_MAX;
        }

        return $page;
    }

    /**
     * @return mixed[]
     */
    private function getDefaultParams(DataProviderResolverInterface $provider): array
    {
        $providerConfiguration = $provider->getProviderConfiguration();

        $defaults = [
            'provider' => new PropertyParameter('provider', 'pages'),
            'alias' => null,
            'page_parameter' => new PropertyParameter('page_parameter', 'p'),
            'tags_parameter' => new PropertyParameter('tags_parameter', 'tags'),
            'categories_parameter' => new PropertyParameter('categories_parameter', 'categories'),
            'website_tags_operator' => new PropertyParameter('website_tags_operator', 'OR'),
            'website_categories_operator' => new PropertyParameter('website_categories_operator', 'OR'),
            'sorting' => new PropertyParameter('sorting', $providerConfiguration->getSorting() ?? [], 'collection'),
            'present_as' => new PropertyParameter('present_as', [], 'collection'),
            'display_options' => new PropertyParameter(
                'display_options',
                [
                    'tags' => new PropertyParameter('tags', true),
                    'categories' => new PropertyParameter('categories', true),
                    'sorting' => new PropertyParameter('sorting', true),
                    'limit' => new PropertyParameter('limit', true),
                    'presentAs' => new PropertyParameter('presentAs', true),
                ],
                'collection'
            ),
            'has' => [
                'datasource' => $providerConfiguration->hasDatasource(),
                'tags' => $providerConfiguration->hasTags(),
                'categories' => $providerConfiguration->hasCategories(),
                'sorting' => $providerConfiguration->hasSorting(),
                'limit' => $providerConfiguration->hasLimit(),
                'presentAs' => $providerConfiguration->hasPresentAs(),
                'audienceTargeting' => $providerConfiguration->hasAudienceTargeting(),
            ],
            'datasourceResourceKey' => $providerConfiguration->getDatasourceResourceKey(),
            'datasourceAdapter' => $providerConfiguration->getDatasourceAdapter(),
            'exclude_duplicates' => new PropertyParameter('exclude_duplicates', false),
        ];

        return \array_merge(
            $defaults,
            $provider->getProviderDefaultParams()
        );
    }
}
