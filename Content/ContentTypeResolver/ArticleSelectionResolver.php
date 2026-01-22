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

use Sulu\Article\Domain\Repository\ArticleRepositoryInterface;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\StructureResolverInterface;
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;

class ArticleSelectionResolver implements ContentTypeResolverInterface
{
    public static function getContentType(): string
    {
        return 'article_selection';
    }

    public function __construct(
        private StructureResolverInterface $structureResolver,
        private ArticleRepositoryInterface $articleRepository,
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
        $articles = $this->articleRepository->findBy(
            [
                'uuids' => $data,
                'locale' => $locale,
                'stage' => $stage,
            ],
            [],
            [ArticleRepositoryInterface::GROUP_SELECT_ARTICLE_WEBSITE => true],
        );

        $resolvedArticles = \array_fill_keys($data, null);
        foreach ($articles as $article) {
            $dimensionContent = $this->contentAggregator->aggregate(
                $article,
                ['locale' => $locale, 'stage' => $stage],
            );

            $resolvedArticles[$article->getUuid()] = $this->structureResolver->resolveProperties(
                $dimensionContent,
                $propertyMap,
                $locale,
            );
        }

        return new ContentView(\array_values(\array_filter($resolvedArticles)), ['ids' => $data]);
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
