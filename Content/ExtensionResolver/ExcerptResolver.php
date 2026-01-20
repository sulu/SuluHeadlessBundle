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
use Sulu\Content\Domain\Model\ExcerptInterface;
use Sulu\Content\Domain\Model\TaxonomyInterface;

class ExcerptResolver implements ExtensionResolverInterface
{
    public const PREFIX = 'excerpt.';

    private const FIELD_MAPPING = [
        'tags' => 'excerptTags',
        'categories' => 'excerptCategories',
    ];

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
        if (!$dimensionContent instanceof ExcerptInterface && !$dimensionContent instanceof TaxonomyInterface) {
            return new ContentView([], []);
        }

        $excerptProperties = $this->filterProperties($properties);
        if ([] === $excerptProperties) {
            return new ContentView([], []);
        }

        $formMetadata = $this->formMetadataProvider->getMetadata(
            'content_excerpt',
            $locale,
            ['instanceOf' => $dimensionContent::class]
        );

        if (!$formMetadata instanceof FormMetadata) {
            return new ContentView([], []);
        }

        $fieldMetadataList = $formMetadata->getFlatFieldMetadata();
        $excerptData = $this->getExcerptTaxonomyData($dimensionContent);

        $content = [];
        $view = [];

        foreach ($excerptProperties as $targetKey => $fieldInfo) {
            $formFieldName = $fieldInfo['formField'];
            $dataKey = $fieldInfo['dataKey'];

            $fieldMetadata = $fieldMetadataList[$formFieldName] ?? null;

            if (null === $fieldMetadata) {
                continue;
            }

            $value = $excerptData[$dataKey] ?? null;

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
    public function hasExcerptProperties(array $properties): bool
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
     * @return array<string, array{formField: string, dataKey: string}>
     */
    private function filterProperties(array $properties): array
    {
        $filtered = [];
        foreach ($properties as $targetKey => $sourceKey) {
            if (\str_starts_with($sourceKey, self::PREFIX)) {
                $fieldSuffix = \substr($sourceKey, \strlen(self::PREFIX));

                if (isset(self::FIELD_MAPPING[$fieldSuffix])) {
                    $filtered[$targetKey] = [
                        'formField' => self::FIELD_MAPPING[$fieldSuffix],
                        'dataKey' => self::FIELD_MAPPING[$fieldSuffix],
                    ];
                } else {
                    $filtered[$targetKey] = [
                        'formField' => 'excerpt/' . $fieldSuffix,
                        'dataKey' => 'excerpt/' . $fieldSuffix,
                    ];
                }
            }
        }

        return $filtered;
    }

    /**
     * @return array<string, mixed>
     */
    private function getExcerptTaxonomyData(DimensionContentInterface $dimensionContent): array
    {
        $data = [];

        if ($dimensionContent instanceof ExcerptInterface) {
            foreach ($dimensionContent->getExcerptData() as $fieldName => $value) {
                $data['excerpt/' . $fieldName] = $value;
            }
        }

        if ($dimensionContent instanceof TaxonomyInterface) {
            $data['excerptCategories'] = $dimensionContent->getExcerptCategoryIds();
            $data['excerptTags'] = $dimensionContent->getExcerptTagNames();
        }

        return $data;
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
