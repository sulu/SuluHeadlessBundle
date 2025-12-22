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

namespace Sulu\Bundle\HeadlessBundle\Controller;

use Sulu\Bundle\HttpCacheBundle\Cache\SuluHttpCache;
use Sulu\Bundle\HttpCacheBundle\ReferenceStore\ReferenceStoreInterface;
use Sulu\Component\Rest\RequestParametersTrait;
use Sulu\Component\Webspace\Analyzer\Attributes\RequestAttributes;
use Sulu\Component\Webspace\Segment;
use Sulu\Component\Webspace\Webspace;
use Sulu\Page\Domain\Repository\NavigationRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class NavigationController
{
    use RequestParametersTrait;

    public function __construct(
        private NavigationRepositoryInterface $navigationRepository,
        private ReferenceStoreInterface $navigationReferenceStore,
        private int $maxAge,
        private int $sharedMaxAge,
        private int $cacheLifetime,
    ) {
    }

    public function getAction(Request $request, string $context): Response
    {
        /** @var RequestAttributes $attributes */
        $attributes = $request->attributes->get('_sulu');

        /** @var Webspace $webspace */
        $webspace = $attributes->getAttribute('webspace');
        $webspaceKey = $webspace->getKey();
        $locale = $request->getLocale();

        /** @var Segment|null $segment */
        $segment = $attributes->getAttribute('segment');
        $segmentKey = $segment?->getKey();

        /** @var string|null $uuid */
        $uuid = $request->query->get('uuid');
        $depth = (int) $this->getRequestParameter($request, 'depth', false, 1);
        $flat = $this->getBooleanRequestParameter($request, 'flat', false, false);
        $excerpt = $this->getBooleanRequestParameter($request, 'excerpt', false, false);

        // Build properties to fetch
        $properties = $this->buildProperties($locale, $webspaceKey, $excerpt);

        // Load navigation
        $navigation = $this->loadNavigation(
            $webspaceKey,
            $locale,
            $segmentKey,
            $depth,
            $flat,
            $context,
            $properties,
            $uuid
        );

        // Transform navigation items to match expected format
        $transformedNavigation = $this->transformNavigationItems($navigation, $locale);

        // Add to reference store for cache invalidation
        $this->navigationReferenceStore->add($context, 'navigation');

        $response = new JsonResponse([
            '_embedded' => [
                'items' => $transformedNavigation,
            ],
        ]);

        $response->setPublic();
        $response->setMaxAge($this->maxAge);
        $response->setSharedMaxAge($this->sharedMaxAge);
        $response->headers->set(SuluHttpCache::HEADER_REVERSE_PROXY_TTL, (string) $this->cacheLifetime);

        return $response;
    }

    /**
     * @param array<string, string> $properties
     *
     * @return array<int, array<string, mixed>>
     */
    protected function loadNavigation(
        string $webspaceKey,
        string $locale,
        ?string $segmentKey,
        int $depth,
        bool $flat,
        string $context,
        array $properties,
        ?string $uuid = null,
    ): array {
        if ($uuid) {
            if ($flat) {
                return $this->navigationRepository->getNavigationFlatByUuid(
                    $uuid,
                    $locale,
                    $webspaceKey,
                    $depth,
                    $context,
                    $properties
                );
            }

            return $this->navigationRepository->getNavigationTreeByUuid(
                $uuid,
                $locale,
                $webspaceKey,
                $depth,
                $context,
                $properties
            );
        }

        if ($flat) {
            return $this->navigationRepository->getNavigationFlat(
                $context,
                $locale,
                $webspaceKey,
                $segmentKey,
                $depth,
                $properties
            );
        }

        return $this->navigationRepository->getNavigationTree(
            $context,
            $locale,
            $webspaceKey,
            $segmentKey,
            $depth,
            $properties
        );
    }

    /**
     * Build properties to fetch based on options.
     *
     * @return array<string, string>
     */
    private function buildProperties(string $locale, string $webspaceKey, bool $excerpt): array
    {
        $properties = [
            'id' => 'object.resource.uuid',
            'uuid' => 'object.resource.uuid',
            'title' => 'title',
            'url' => 'url',
            'publishedState' => 'object.workflowPublished',
            'published' => 'object.workflowPublished',
            'author' => 'object.author.id',
            'authored' => 'object.authored',
            'changed' => 'object.resource.changed',
            'changer' => 'object.resource.changer.id',
            'created' => 'object.resource.created',
            'creator' => 'object.resource.creator.id',
            'lastModified' => 'object.lastModified',
            'template' => 'object.templateKey',
            'locale' => 'object.locale',
            'webspaceKey' => 'object.resource.webspaceKey',
            'order' => 'object.resource.lft',
            'parent' => 'object.resource.parent.uuid',
            'urls' => 'urls',
        ];

        if ($excerpt) {
            $properties = \array_merge($properties, [
                'excerpt.title' => 'excerpt.title',
                'excerpt.description' => 'excerpt.description',
                'excerpt.more' => 'excerpt.more',
                'excerpt.icon' => 'excerpt.icon',
                'excerpt.images' => 'excerpt.images',
                'excerpt.categories' => 'excerpt.categories',
                'excerpt.tags' => 'excerpt.tags',
            ]);
        }

        return $properties;
    }

    /**
     * Transform navigation items to match expected response format.
     *
     * @param array<int, array<string, mixed>> $items
     *
     * @return array<int, array<string, mixed>>
     */
    private function transformNavigationItems(array $items, string $locale): array
    {
        $result = [];

        foreach ($items as $item) {
            $result[] = $this->transformNavigationItem($item, $locale);
        }

        return $result;
    }

    /**
     * Transform a single navigation item.
     *
     * @param array<string, mixed> $item
     *
     * @return array<string, mixed>
     */
    private function transformNavigationItem(array $item, string $locale): array
    {
        $transformed = [];

        // Core properties
        $transformed['id'] = $item['id'] ?? $item['uuid'] ?? null;
        $transformed['uuid'] = $item['uuid'] ?? $item['id'] ?? null;
        $transformed['title'] = $item['title'] ?? '';
        $transformed['url'] = $item['url'] ?? '';
        $transformed['template'] = $item['template'] ?? 'default';
        $transformed['locale'] = $item['locale'] ?? $locale;
        $transformed['webspaceKey'] = $item['webspaceKey'] ?? null;
        $transformed['order'] = $item['order'] ?? null;
        $transformed['parent'] = $item['parent'] ?? null;

        // Transform dates to ISO format
        $transformed['published'] = $this->formatDate($item['published'] ?? null);
        $transformed['publishedState'] = null !== ($item['publishedState'] ?? null);
        $transformed['authored'] = $this->formatDate($item['authored'] ?? null);
        $transformed['changed'] = $this->formatDate($item['changed'] ?? null);
        $transformed['created'] = $this->formatDate($item['created'] ?? null);
        $transformed['lastModified'] = $this->formatDate($item['lastModified'] ?? null);

        // User IDs
        $transformed['author'] = $item['author'] ?? null;
        $transformed['changer'] = $item['changer'] ?? null;
        $transformed['creator'] = $item['creator'] ?? null;

        // URLs map
        $transformed['urls'] = $item['urls'] ?? [$locale => $item['url'] ?? ''];

        // Recursively transform children
        $children = $item['children'] ?? [];
        $transformed['children'] = $this->transformNavigationItems($children, $locale);

        // Add excerpt data if present (either nested or flat key format)
        $excerptData = $item['excerpt'] ?? null;

        // Handle flat key format (excerpt.title, excerpt.description, etc.)
        if (null === $excerptData) {
            $hasExcerptFlatKeys = \array_key_exists('excerpt.title', $item)
                || \array_key_exists('excerpt.description', $item)
                || \array_key_exists('excerpt.more', $item)
                || \array_key_exists('excerpt.icon', $item)
                || \array_key_exists('excerpt.images', $item)
                || \array_key_exists('excerpt.categories', $item)
                || \array_key_exists('excerpt.tags', $item);

            if ($hasExcerptFlatKeys) {
                $excerptData = [
                    'title' => $item['excerpt.title'] ?? '',
                    'description' => $item['excerpt.description'] ?? '',
                    'more' => $item['excerpt.more'] ?? '',
                    'icon' => $item['excerpt.icon'] ?? [],
                    'images' => $item['excerpt.images'] ?? [],
                    'categories' => $item['excerpt.categories'] ?? [],
                    'tags' => $item['excerpt.tags'] ?? [],
                    'audience_targeting_groups' => $item['excerpt.audience_targeting_groups'] ?? [],
                    'segments' => $item['excerpt.segments'] ?? [],
                ];
            }
        }

        if (null !== $excerptData && \is_array($excerptData)) {
            $transformed['excerpt'] = [
                'title' => $excerptData['title'] ?? '',
                'description' => $excerptData['description'] ?? '',
                'more' => $excerptData['more'] ?? '',
                'icon' => $this->ensureIndexedArray($excerptData['icon'] ?? []),
                'images' => $this->ensureIndexedArray($excerptData['images'] ?? $excerptData['image'] ?? []),
                'categories' => $this->ensureIndexedArray($excerptData['categories'] ?? []),
                'tags' => $this->ensureIndexedArray($excerptData['tags'] ?? []),
                'audience_targeting_groups' => $this->ensureIndexedArray($excerptData['audience_targeting_groups'] ?? $excerptData['audienceTargetingGroups'] ?? []),
                'segments' => $this->ensureIndexedArray($excerptData['segments'] ?? $excerptData['segment'] ?? []),
            ];
        }

        return $transformed;
    }

    /**
     * Format a date value to ISO8601 string.
     */
    private function formatDate(mixed $date): ?string
    {
        if (null === $date) {
            return null;
        }

        if ($date instanceof \DateTimeInterface) {
            return $date->format(\DateTimeInterface::ISO8601);
        }

        if (\is_string($date)) {
            return $date;
        }

        return null;
    }

    /**
     * Ensure value is an indexed array (not associative) for proper JSON encoding.
     *
     * @return array<int, mixed>
     */
    private function ensureIndexedArray(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        // If already empty, return empty indexed array
        if ([] === $value) {
            return [];
        }

        // Check if associative array (non-sequential integer keys)
        if (\array_keys($value) !== \range(0, \count($value) - 1)) {
            // Wrap single item in array if it looks like a single media/entity
            if (isset($value['id']) || isset($value['title'])) {
                return [$value];
            }

            // For other associative arrays, return values as indexed array
            return \array_values($value);
        }

        return $value;
    }
}
