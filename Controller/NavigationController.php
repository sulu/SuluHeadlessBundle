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

use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\CategorySerializerInterface;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\MediaSerializerInterface;
use Sulu\Bundle\HttpCacheBundle\Cache\SuluHttpCache;
use Sulu\Bundle\HttpCacheBundle\ReferenceStore\ReferenceStoreInterface;
use Sulu\Component\Rest\ListBuilder\CollectionRepresentation;
use Sulu\Component\Webspace\Analyzer\RequestAnalyzerInterface;
use Sulu\Component\Webspace\Segment;
use Sulu\Page\Domain\Repository\NavigationRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class NavigationController
{
    public function __construct(
        private NavigationRepositoryInterface $navigationRepository,
        private SerializerInterface $serializer,
        private ReferenceStoreInterface $navigationReferenceStore,
        private RequestAnalyzerInterface $requestAnalyzer,
        private MediaSerializerInterface $mediaSerializer,
        private CategorySerializerInterface $categorySerializer,
        private int $maxAge,
        private int $sharedMaxAge,
        private int $cacheLifetime,
    ) {
    }

    public function getAction(Request $request, string $context): Response
    {
        $webspace = $this->requestAnalyzer->getWebspace();
        if (null === $webspace) {
            throw new \RuntimeException('No webspace found in request.');
        }

        $locale = $request->getLocale();
        $uuid = $request->query->get('uuid');
        $depth = $request->query->getInt('depth', 1);
        $flat = $request->query->getBoolean('flat');
        $excerpt = $request->query->getBoolean('excerpt');

        /** @var Segment|null $segment */
        $segment = $this->requestAnalyzer->getSegment();
        $segmentKey = $segment?->getKey();

        $navigation = $this->loadNavigation(
            $webspace->getKey(),
            $locale,
            $segmentKey,
            $depth,
            $flat,
            $context,
            $this->getProperties($excerpt),
            $uuid
        );

        $transformedNavigation = $this->transformNavigationItems($navigation, $locale, $excerpt);
        $response = JsonResponse::fromJsonString(
            $this->serializer->serialize(
                new CollectionRepresentation($transformedNavigation, 'items'),
                'json',
                (new SerializationContext())->setSerializeNull(true)
            )
        );

        $response->setPublic();
        $response->setMaxAge($this->maxAge);
        $response->setSharedMaxAge($this->sharedMaxAge);
        $response->headers->set(SuluHttpCache::HEADER_REVERSE_PROXY_TTL, (string) $this->cacheLifetime);

        $this->navigationReferenceStore->add($context, 'navigation');

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
        return match (true) {
            null !== $uuid && $flat => $this->navigationRepository->getNavigationFlatByUuid(
                $uuid,
                $locale,
                $webspaceKey,
                $depth,
                $context,
                $properties
            ),
            null !== $uuid => $this->navigationRepository->getNavigationTreeByUuid(
                $uuid,
                $locale,
                $webspaceKey,
                $depth,
                $context,
                $properties
            ),
            $flat => $this->navigationRepository->getNavigationFlat(
                $context,
                $locale,
                $webspaceKey,
                $segmentKey,
                $depth,
                $properties
            ),
            default => $this->navigationRepository->getNavigationTree(
                $context,
                $locale,
                $webspaceKey,
                $segmentKey,
                $depth,
                $properties
            ),
        };
    }

    /**
     * @param array<int, array<string, mixed>> $items
     *
     * @return array<int, array<string, mixed>>
     */
    protected function transformNavigationItems(array $items, string $locale, bool $excerpt): array
    {
        return \array_map(
            fn (array $item) => $this->transformNavigationItem($item, $locale, $excerpt),
            $items
        );
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array<string, mixed>
     */
    protected function transformNavigationItem(array $item, string $locale, bool $excerpt): array
    {
        $transformed = [];
        $transformed['id'] = $item['id'] ?? $item['uuid'] ?? null;
        $transformed['uuid'] = $item['uuid'] ?? $item['id'] ?? null;
        $transformed['linkType'] = $item['linkType'] ?? null;
        $transformed['title'] = $item['title'] ?? '';
        $transformed['url'] = $item['url'] ?? '';
        $transformed['template'] = $item['template'] ?? 'default';
        $transformed['locale'] = $item['locale'] ?? $locale;
        $transformed['webspaceKey'] = $item['webspaceKey'] ?? null;
        $transformed['order'] = $item['order'] ?? null;
        $transformed['parent'] = $item['parent'] ?? null;
        $transformed['published'] = $this->formatDate($item['published'] ?? null);
        $transformed['publishedState'] = null !== ($item['publishedState'] ?? null);
        $transformed['authored'] = $this->formatDate($item['authored'] ?? null);
        $transformed['changed'] = $this->formatDate($item['changed'] ?? null);
        $transformed['created'] = $this->formatDate($item['created'] ?? null);
        $transformed['lastModified'] = $this->formatDate($item['lastModified'] ?? null);
        $transformed['author'] = $item['author'] ?? null;
        $transformed['changer'] = $item['changer'] ?? null;
        $transformed['creator'] = $item['creator'] ?? null;
        $transformed['urls'] = $item['urls'] ?? [$locale => $item['url'] ?? ''];

        if ($excerpt && isset($item['excerpt']) && \is_array($item['excerpt'])) {
            $transformed['excerpt'] = $this->transformExcerpt($item['excerpt'], $locale);
        }

        /** @var array<int, array<string, mixed>> $children */
        $children = $item['children'] ?? [];
        $transformed['children'] = $this->transformNavigationItems($children, $locale, $excerpt);

        return $transformed;
    }

    /**
     * @param array<string, mixed> $excerptData
     *
     * @return array<string, mixed>
     */
    protected function transformExcerpt(array $excerptData, string $locale): array
    {
        $icon = $excerptData['icon'] ?? null;
        $image = $excerptData['image'] ?? null;
        $categories = $excerptData['categories'] ?? [];

        return [
            'title' => $excerptData['title'] ?? '',
            'description' => $excerptData['description'] ?? '',
            'more' => $excerptData['more'] ?? '',
            'icon' => \is_object($icon) && \method_exists($icon, 'getEntity')
                ? $this->mediaSerializer->serialize($icon->getEntity(), $locale)
                : null,
            'image' => \is_object($image) && \method_exists($image, 'getEntity')
                ? $this->mediaSerializer->serialize($image->getEntity(), $locale)
                : null,
            'categories' => \is_array($categories) ? \array_map(
                fn ($category) => $this->categorySerializer->serialize($category->getEntity(), $locale),
                $categories
            ) : [],
            'tags' => $excerptData['tags'] ?? [],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function getProperties(bool $excerpt): array
    {
        $properties = [
            'id' => 'object.resource.uuid',
            'uuid' => 'object.resource.uuid',
            'title' => 'title',
            'url' => 'url',
            'linkType' => 'object.linkData.provider',
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
                'excerpt.image' => 'excerpt.image',
                'excerpt.categories' => 'excerpt.categories',
                'excerpt.tags' => 'excerpt.tags',
            ]);
        }

        return $properties;
    }

    protected function formatDate(mixed $date): ?string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format(\DateTimeInterface::ATOM);
        }

        return \is_string($date) ? $date : null;
    }
}
