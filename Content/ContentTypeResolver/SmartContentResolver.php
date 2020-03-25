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

use Sulu\Bundle\AudienceTargetingBundle\TargetGroup\TargetGroupStoreInterface;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\DataProviderResolver\DataProviderResolverInterface;
use Sulu\Bundle\TagBundle\Tag\TagManagerInterface;
use Sulu\Component\Category\Request\CategoryRequestHandlerInterface;
use Sulu\Component\Content\Compat\PropertyInterface;
use Sulu\Component\Content\Compat\PropertyParameter;
use Sulu\Component\SmartContent\DataProviderAliasInterface;
use Sulu\Component\SmartContent\Exception\PageOutOfBoundsException;
use Sulu\Component\Tag\Request\TagRequestHandlerInterface;
use Sulu\Exception\FeatureNotImplementedException;
use Symfony\Component\HttpFoundation\RequestStack;

class SmartContentResolver implements ContentTypeResolverInterface
{
    public static function getContentType(): string
    {
        return 'smart_content';
    }

    /**
     * @var DataProviderResolverInterface[]
     */
    private $resolvers;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var TagManagerInterface
     */
    private $tagManager;

    /**
     * @var TagRequestHandlerInterface
     */
    private $tagRequestHandler;

    /**
     * @var CategoryRequestHandlerInterface
     */
    private $categoryRequestHandler;

    /**
     * @var ?TargetGroupStoreInterface
     */
    private $targetGroupStore;

    public function __construct(
        \Traversable $resolvers,
        TagManagerInterface $tagManager,
        RequestStack $requestStack,
        TagRequestHandlerInterface $tagRequestHandler,
        CategoryRequestHandlerInterface $categoryRequestHandler,
        ?TargetGroupStoreInterface $targetGroupStore = null
    ) {
        $this->resolvers = iterator_to_array($resolvers);
        $this->tagManager = $tagManager;
        $this->requestStack = $requestStack;
        $this->tagRequestHandler = $tagRequestHandler;
        $this->categoryRequestHandler = $categoryRequestHandler;
        $this->targetGroupStore = $targetGroupStore;
    }

    public function resolve($result, PropertyInterface $property, string $locale, array $attributes = []): ContentView
    {
        // gather data provider and effective parameters
        $providerResolver = $this->getProviderResolver($property);
        /** @var PropertyParameter[] $params */
        $params = array_merge(
            $this->getDefaultParams($providerResolver),
            $property->getParams()
        );

        // prepare filters
        $filters = $property->getValue();
        $filters['excluded'] = [$property->getStructure()->getUuid()];

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
            'webspaceKey' => $property->getStructure()->getWebspaceKey(),
            'locale' => $property->getStructure()->getLanguageCode(),
        ];

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

            if ($page > 1 && 0 === \count($result->getItems())) {
                throw new PageOutOfBoundsException($page);
            }
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

    private function getProviderResolver(PropertyInterface $property): DataProviderResolverInterface
    {
        $params = $property->getParams();

        $providerAlias = 'pages';
        if (\array_key_exists('provider', $params)) {
            /** @var string $providerAlias */
            $providerAlias = $params['provider']->getValue();
        }

        if (!\array_key_exists($providerAlias, $this->resolvers)) {
            throw new FeatureNotImplementedException();
        }

        return $this->resolvers[$providerAlias];
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

        // a tag identifier might be a name or an id
        $ids = [];
        $names = [];

        foreach ($tagIdentifiers as $tagIdentifier) {
            if (is_numeric($tagIdentifier)) {
                $ids[] = $tagIdentifier;
            } else {
                $names[] = $tagIdentifier;
            }
        }

        if (!empty($names)) {
            $ids = array_merge($ids, $this->tagManager->resolveTagNames($names));
        }

        return $ids;
    }

    private function getCurrentPage(string $pageParameter): int
    {
        if (null === $this->requestStack->getCurrentRequest()) {
            return 1;
        }

        $page = $this->requestStack->getCurrentRequest()->get($pageParameter, 1);
        if ($page < 1 || $page > PHP_INT_MAX) {
            throw new PageOutOfBoundsException($page);
        }

        return (int) $page;
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

        if ($provider instanceof DataProviderAliasInterface) {
            $defaults['alias'] = $provider->getAlias();
        }

        return array_merge(
            $defaults,
            $provider->getProviderDefaultParams()
        );
    }
}
