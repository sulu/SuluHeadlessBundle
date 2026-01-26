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

namespace Sulu\Bundle\HeadlessBundle\Content\ExtensionResolver;

use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderInterface;
use Sulu\Bundle\HeadlessBundle\Content\ContentResolverInterface;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Domain\Model\SeoInterface;

class SeoResolver implements ExtensionResolverInterface
{
    public const PREFIX = 'seo.';

    public function __construct(
        private MetadataProviderInterface $formMetadataProvider,
        private ContentResolverInterface $contentResolver,
    ) {
    }

    public function getPrefix(): string
    {
        return self::PREFIX;
    }

    /**
     * @param array<string, string> $properties
     * @param array<string, mixed> $attributes
     */
    public function resolve(
        DimensionContentInterface $dimensionContent,
        array $properties,
        string $locale,
        array $attributes = [],
    ): ContentView {
        if (!$dimensionContent instanceof SeoInterface) {
            return new ContentView([], []);
        }

        $seoProperties = $this->filterProperties($properties);
        if ([] === $seoProperties) {
            return new ContentView([], []);
        }

        $formMetadata = $this->formMetadataProvider->getMetadata(
            'content_seo',
            $locale,
            ['instanceOf' => SeoInterface::class]
        );

        $fieldMetadataList = [];
        if ($formMetadata instanceof FormMetadata) {
            $fieldMetadataList = \array_filter(
                $formMetadata->getFlatFieldMetadata(),
                static fn ($item) => !\in_array($item->getType(), ['search_result'], true)
            );
        }

        $seoData = $this->getSeoData($dimensionContent);

        $content = [];
        $view = [];

        foreach ($seoProperties as $targetKey => $normalizedSourceKey) {
            $fieldName = \substr($normalizedSourceKey, \strlen('seo/'));

            if (\array_key_exists($fieldName, $this->getBooleanFields($dimensionContent))) {
                $content[$targetKey] = $this->getBooleanFields($dimensionContent)[$fieldName];
                $view[$targetKey] = [];

                continue;
            }

            $fieldMetadata = $fieldMetadataList[$normalizedSourceKey] ?? null;

            if (null === $fieldMetadata) {
                continue;
            }

            $value = $seoData[$fieldName] ?? null;

            $contentView = $this->contentResolver->resolve($value, $fieldMetadata, $locale, $attributes);
            $resolvedContent = $contentView->getContent();

            if (null === $resolvedContent) {
                $resolvedContent = $this->getEmptyValue($fieldMetadata->getType());
            }

            $content[$targetKey] = $resolvedContent;
            $view[$targetKey] = $contentView->getView();
        }

        return new ContentView($content, $view);
    }

    /**
     * @param array<string, string> $properties
     */
    public function hasSeoProperties(array $properties): bool
    {
        foreach ($properties as $sourceKey) {
            if (\str_starts_with($sourceKey, self::PREFIX)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, string> $properties
     *
     * @return array<string, string>
     */
    private function filterProperties(array $properties): array
    {
        $filtered = [];
        foreach ($properties as $targetKey => $sourceKey) {
            if (\str_starts_with($sourceKey, self::PREFIX)) {
                $normalizedValue = 'seo/' . \substr($sourceKey, \strlen(self::PREFIX));
                $filtered[$targetKey] = $normalizedValue;
            }
        }

        return $filtered;
    }

    /**
     * @return array<string, mixed>
     */
    private function getSeoData(SeoInterface $dimensionContent): array
    {
        return $dimensionContent->getSeoData();
    }

    /**
     * @return array<string, bool>
     */
    private function getBooleanFields(SeoInterface $dimensionContent): array
    {
        return [
            'noIndex' => $dimensionContent->getSeoNoIndex(),
            'noFollow' => $dimensionContent->getSeoNoFollow(),
            'hideInSitemap' => $dimensionContent->getSeoHideInSitemap(),
        ];
    }

    private function getEmptyValue(string $fieldType): mixed
    {
        $arrayTypes = [
            'single_media_selection',
            'media_selection',
            'category_selection',
            'tag_selection',
            'snippet_selection',
            'page_selection',
            'segment_select',
        ];

        if (\in_array($fieldType, $arrayTypes, true)) {
            return [];
        }

        return '';
    }
}
