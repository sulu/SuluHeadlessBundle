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
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\StructureResolverInterface;
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;

class PageSelectionResolver implements ContentTypeResolverInterface
{
    public static function getContentType(): string
    {
        return 'page_selection';
    }

    public function __construct(
        private StructureResolverInterface $structureResolver,
        private PageRepositoryInterface $pageRepository,
        private ContentAggregatorInterface $contentAggregator,
        private bool $showDrafts,
    ) {
    }

    public function resolve(mixed $data, FieldMetadata $fieldMetadata, string $locale, array $attributes = []): ContentView
    {
        if (empty($data) || !\is_array($data)) {
            return new ContentView([], ['ids' => []]);
        }

        $propertyMap = $this->getPropertyMap($fieldMetadata);

        $stage = $this->showDrafts ? DimensionContentInterface::STAGE_DRAFT : DimensionContentInterface::STAGE_LIVE;
        $pages = $this->pageRepository->findBy(
            [
                'uuids' => $data,
                'locale' => $locale,
                'stage' => $stage,
            ],
            [],
            [PageRepositoryInterface::GROUP_SELECT_PAGE_WEBSITE => true],
        );

        $resolvedPages = \array_fill_keys($data, null);
        foreach ($pages as $page) {
            $dimensionContent = $this->contentAggregator->aggregate(
                $page,
                ['locale' => $locale, 'stage' => $stage],
            );

            $resolvedPages[$page->getUuid()] = $this->structureResolver->resolveProperties(
                $dimensionContent,
                $propertyMap,
                $locale,
            );
        }

        return new ContentView(\array_values(\array_filter($resolvedPages)), ['ids' => $data]);
    }

    /**
     * @return array<string, string>
     */
    private function getPropertyMap(FieldMetadata $fieldMetadata): array
    {
        $propertyMap = [
            'title' => 'title',
            'url' => 'url',
        ];

        foreach ($fieldMetadata->getOptions() as $option) {
            if ('properties' === $option->getName()) {
                $propertiesValue = $option->getValue();
                if (\is_array($propertiesValue)) {
                    foreach ($propertiesValue as $entry) {
                        $paramName = $entry->getName();
                        if (\is_string($paramName)) {
                            $paramValue = $entry->getValue() ?? $paramName;
                            $propertyMap[$paramName] = \is_string($paramValue) ? $paramValue : $paramName;
                        }
                    }
                }
                break;
            }
        }

        return $propertyMap;
    }
}
