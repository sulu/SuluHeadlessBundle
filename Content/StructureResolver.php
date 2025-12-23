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

namespace Sulu\Bundle\HeadlessBundle\Content;

use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TypedFormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderInterface;
use Sulu\Bundle\HeadlessBundle\Content\Resolver\PropertyPathParser;
use Sulu\Bundle\HttpCacheBundle\ReferenceStore\ReferenceStoreInterface;
use Sulu\Content\Domain\Model\AuthorInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Domain\Model\ExcerptInterface;
use Sulu\Content\Domain\Model\SeoInterface;
use Sulu\Content\Domain\Model\ShadowInterface;
use Sulu\Content\Domain\Model\TaxonomyInterface;
use Sulu\Content\Domain\Model\TemplateInterface;

class StructureResolver implements StructureResolverInterface
{
    public function __construct(
        private MetadataProviderInterface $formMetadataProvider,
        private ContentResolverInterface $contentResolver,
        private ReferenceStoreInterface $referenceStore,
        private PropertyPathParser $propertyPathParser,
    ) {
    }

    /**
     * @param array<string, string>|null $properties Optional property map to resolve only specific properties
     */
    public function resolve(
        DimensionContentInterface $dimensionContent,
        string $locale,
        bool $includeExtension = true,
        ?array $properties = null,
    ): array {
        $resource = $dimensionContent->getResource();
        $resourceId = $resource->getId();

        // Get template type and key
        $templateType = 'unknown';
        $templateKey = null;
        if ($dimensionContent instanceof TemplateInterface) {
            $templateType = $dimensionContent::getTemplateType();
            $templateKey = $dimensionContent->getTemplateKey();
        }

        // Add to reference store
        $this->addToReferenceStore((string) $resourceId, $templateType);

        // Build attributes with context for resolvers
        $attributes = $this->buildAttributes($dimensionContent);

        // Parse property paths if filtering
        $propertyFilter = null;
        if (null !== $properties) {
            $propertyFilter = $this->propertyPathParser->groupByContext($properties);
        }

        // Resolve template content
        $content = [];
        $view = [];

        if ($dimensionContent instanceof TemplateInterface && null !== $templateKey) {
            $templateFilter = $propertyFilter['template'] ?? null;
            [$content, $view] = $this->resolveTemplateContent(
                $dimensionContent,
                $templateType,
                $templateKey,
                $locale,
                $attributes,
                $templateFilter,
            );
        }

        // Build base structure matching the expected JSON format
        $data = [
            'id' => $resourceId,
            'type' => $templateType,
            'template' => $templateKey,
            'content' => $content,
            'view' => $view,
        ];

        // Add extension data if requested (with property filtering support)
        if ($includeExtension) {
            $data['extension'] = $this->resolveExtensions(
                $dimensionContent,
                $locale,
                $attributes,
                $propertyFilter
            );
        }

        // Add author/timestamps if available
        if ($dimensionContent instanceof AuthorInterface) {
            $author = $dimensionContent->getAuthor();
            $authored = $dimensionContent->getAuthored();

            $data['author'] = $author?->getId();
            $data['authored'] = $authored?->format(\DateTimeImmutable::ISO8601);
        }

        // Add audit trail from resource if available (trust interface contracts)
        if (\method_exists($resource, 'getChanger')) {
            $changer = $resource->getChanger();
            $data['changer'] = $changer?->getId();
        }

        if (\method_exists($resource, 'getChanged')) {
            $changed = $resource->getChanged();
            $data['changed'] = $changed?->format(\DateTimeImmutable::ISO8601);
        }

        if (\method_exists($resource, 'getCreator')) {
            $creator = $resource->getCreator();
            $data['creator'] = $creator?->getId();
        }

        if (\method_exists($resource, 'getCreated')) {
            $created = $resource->getCreated();
            $data['created'] = $created?->format(\DateTimeImmutable::ISO8601);
        }

        return $data;
    }

    /**
     * Resolve only specific properties from the dimension content.
     *
     * @param array<string, string> $propertyMap Map of target property names to source property names
     */
    public function resolveProperties(
        DimensionContentInterface $dimensionContent,
        array $propertyMap,
        string $locale,
        bool $includeExtension = false,
    ): array {
        return $this->resolve($dimensionContent, $locale, $includeExtension, $propertyMap);
    }

    /**
     * Resolve template content using FormMetadata.
     *
     * @param array<string, mixed> $attributes
     * @param array<string, string>|null $properties
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function resolveTemplateContent(
        TemplateInterface $dimensionContent,
        string $templateType,
        string $templateKey,
        string $locale,
        array $attributes,
        ?array $properties = null,
    ): array {
        /** @var TypedFormMetadata $typedFormMetadata */
        $typedFormMetadata = $this->formMetadataProvider->getMetadata($templateType, $locale, []);
        $formMetadata = $typedFormMetadata->getForms()[$templateKey] ?? null;

        if (!$formMetadata) {
            return [[], []];
        }

        $fieldMetadataList = $formMetadata->getFlatFieldMetadata();
        $templateData = $dimensionContent->getTemplateData();

        // Filter by properties if specified
        if (null !== $properties) {
            $filteredFieldMetadata = [];
            $filteredTemplateData = [];
            foreach ($properties as $targetKey => $sourceKey) {
                if (\array_key_exists($sourceKey, $fieldMetadataList)) {
                    $filteredFieldMetadata[$targetKey] = $fieldMetadataList[$sourceKey];
                }
                if (\array_key_exists($sourceKey, $templateData)) {
                    $filteredTemplateData[$targetKey] = $templateData[$sourceKey];
                }
            }
            $fieldMetadataList = $filteredFieldMetadata;
            $templateData = $filteredTemplateData;
        }

        // Resolve each field
        $content = [];
        $view = [];
        foreach ($fieldMetadataList as $fieldName => $fieldMetadata) {
            $value = $templateData[$fieldName] ?? null;
            $contentView = $this->contentResolver->resolve($value, $fieldMetadata, $locale, $attributes);
            $content[$fieldName] = $contentView->getContent();
            $view[$fieldName] = $contentView->getView();
        }

        return [$content, $view];
    }

    /**
     * Build attributes with context for resolvers.
     *
     * @return array<string, mixed>
     */
    private function buildAttributes(DimensionContentInterface $dimensionContent): array
    {
        $resource = $dimensionContent->getResource();

        $attributes = [
            'uuid' => $resource->getId(),
        ];

        // Add webspaceKey if available (pages have it on the resource)
        if (\method_exists($resource, 'getWebspaceKey')) {
            $attributes['webspaceKey'] = $resource->getWebspaceKey();
        }

        // Add shadow information
        if ($dimensionContent instanceof ShadowInterface) {
            $shadowLocale = $dimensionContent->getShadowLocale();
            $attributes['isShadow'] = null !== $shadowLocale;
            $attributes['shadowLocale'] = $shadowLocale;
        } else {
            $attributes['isShadow'] = false;
            $attributes['shadowLocale'] = null;
        }

        return $attributes;
    }

    /**
     * Resolve extension data (excerpt, seo) using the form metadata system.
     *
     * @param array<string, mixed> $attributes
     * @param array<string, array<string, string>>|null $propertyFilter Optional property filter grouped by context
     *
     * @return array<string, mixed>
     */
    private function resolveExtensions(
        DimensionContentInterface $dimensionContent,
        string $locale,
        array $attributes,
        ?array $propertyFilter = null,
    ): array {
        $extensions = [];

        // Resolve excerpt data using form metadata with instanceOf option to merge forms
        if ($dimensionContent instanceof ExcerptInterface) {
            $excerptFilter = $propertyFilter['excerpt'] ?? null;
            $extensions['excerpt'] = $this->resolveExcerptData(
                $dimensionContent,
                $locale,
                $attributes,
                $excerptFilter,
            );
        } elseif ($dimensionContent instanceof TaxonomyInterface) {
            // Snippets only implement TaxonomyInterface, not ExcerptInterface
            $excerptFilter = $propertyFilter['excerpt'] ?? null;
            $extensions['excerpt'] = $this->resolveTaxonomyOnlyData(
                $dimensionContent,
                $locale,
                $attributes,
                $excerptFilter,
            );
        }

        // Resolve SEO data using form metadata with instanceOf option to merge forms
        if ($dimensionContent instanceof SeoInterface) {
            $seoFilter = $propertyFilter['seo'] ?? null;
            $extensions['seo'] = $this->resolveSeoData(
                $dimensionContent,
                $locale,
                $attributes,
                $seoFilter,
            );
        }

        return $extensions;
    }

    /**
     * Resolve excerpt data dynamically using merged form metadata.
     *
     * @param array<string, mixed> $attributes
     * @param array<string, string>|null $fieldFilter Optional field filter (target => source field names)
     *
     * @return array<string, mixed>
     */
    private function resolveExcerptData(
        ExcerptInterface $dimensionContent,
        string $locale,
        array $attributes,
        ?array $fieldFilter = null,
    ): array {
        $excerptData = $dimensionContent->getExcerptData();

        // Build raw data including taxonomy data if available
        $rawData = $excerptData;
        if ($dimensionContent instanceof TaxonomyInterface) {
            $rawData['excerptCategories'] = $dimensionContent->getExcerptCategoryIds();
            $rawData['excerptTags'] = $dimensionContent->getExcerptTagNames();
            $rawData['excerptAudienceTargetGroups'] = $dimensionContent->getExcerptAudienceTargetGroupIds();
            $rawData['excerptSegment'] = $dimensionContent->getExcerptSegment();
        }

        try {
            // Get merged form metadata using instanceOf option
            $formMetadata = $this->formMetadataProvider->getMetadata(
                'content_excerpt',
                $locale,
                ['instanceOf' => $dimensionContent::class]
            );
        } catch (\Throwable) {
            return $rawData;
        }

        if (!$formMetadata instanceof FormMetadata) {
            return $rawData;
        }

        return $this->resolveFormFields($formMetadata, $rawData, $locale, $attributes, $fieldFilter);
    }

    /**
     * Resolve taxonomy-only data for entities that implement TaxonomyInterface but not ExcerptInterface.
     *
     * @param array<string, mixed> $attributes
     * @param array<string, string>|null $fieldFilter Optional field filter (target => source field names)
     *
     * @return array<string, mixed>
     */
    private function resolveTaxonomyOnlyData(
        TaxonomyInterface $dimensionContent,
        string $locale,
        array $attributes,
        ?array $fieldFilter = null,
    ): array {
        $data = [
            'categories' => $dimensionContent->getExcerptCategoryIds(),
            'tags' => $dimensionContent->getExcerptTagNames(),
            'audience_targeting_groups' => $dimensionContent->getExcerptAudienceTargetGroupIds(),
            'segments' => $dimensionContent->getExcerptSegment() ?? [],
        ];

        // Apply field filter if provided
        if (null !== $fieldFilter) {
            $filteredData = [];
            foreach ($fieldFilter as $targetKey => $sourceKey) {
                if (\array_key_exists($sourceKey, $data)) {
                    $filteredData[$targetKey] = $data[$sourceKey];
                }
            }

            return $filteredData;
        }

        return $data;
    }

    /**
     * Resolve SEO data dynamically using merged form metadata.
     *
     * @param array<string, mixed> $attributes
     * @param array<string, string>|null $fieldFilter Optional field filter (target => source field names)
     *
     * @return array<string, mixed>
     */
    private function resolveSeoData(
        SeoInterface $dimensionContent,
        string $locale,
        array $attributes,
        ?array $fieldFilter = null,
    ): array {
        $seoData = $dimensionContent->getSeoData();

        // Add boolean properties from interface
        $rawData = $seoData;
        $rawData['seoNoIndex'] = $dimensionContent->getSeoNoIndex();
        $rawData['seoNoFollow'] = $dimensionContent->getSeoNoFollow();
        $rawData['seoHideInSitemap'] = $dimensionContent->getSeoHideInSitemap();

        try {
            // Get merged form metadata using instanceOf option
            $formMetadata = $this->formMetadataProvider->getMetadata(
                'content_seo',
                $locale,
                ['instanceOf' => $dimensionContent::class]
            );
        } catch (\Throwable) {
            return $rawData;
        }

        if (!$formMetadata instanceof FormMetadata) {
            return $rawData;
        }

        return $this->resolveFormFields($formMetadata, $rawData, $locale, $attributes, $fieldFilter);
    }

    /**
     * Resolve form fields using metadata and content resolver.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $attributes
     * @param array<string, string>|null $fieldFilter Optional field filter (target => source field names)
     *
     * @return array<string, mixed>
     */
    private function resolveFormFields(
        FormMetadata $formMetadata,
        array $data,
        string $locale,
        array $attributes,
        ?array $fieldFilter = null,
    ): array {
        $fieldMetadataList = $formMetadata->getFlatFieldMetadata();
        $resolved = [];

        // Field types that are display-only and should not be included in output
        $displayOnlyTypes = ['search_result'];

        // If field filter provided, build the fields to resolve
        $fieldsToResolve = [];
        if (null !== $fieldFilter) {
            foreach ($fieldFilter as $targetKey => $sourceKey) {
                // Find the metadata for this source key
                if (\array_key_exists($sourceKey, $fieldMetadataList)) {
                    $fieldsToResolve[$targetKey] = [
                        'metadata' => $fieldMetadataList[$sourceKey],
                        'sourceKey' => $sourceKey,
                    ];
                }
            }
        } else {
            // No filter - resolve all fields
            foreach ($fieldMetadataList as $fieldName => $fieldMetadata) {
                $fieldsToResolve[$fieldName] = [
                    'metadata' => $fieldMetadata,
                    'sourceKey' => $fieldName,
                ];
            }
        }

        foreach ($fieldsToResolve as $outputKey => $fieldInfo) {
            $fieldMetadata = $fieldInfo['metadata'];
            $sourceKey = $fieldInfo['sourceKey'];

            // Skip display-only fields
            if (\in_array($fieldMetadata->getType(), $displayOnlyTypes, true)) {
                continue;
            }

            // Handle prefixed field names (e.g., excerpt/title -> title, seo/description -> description)
            $dataKey = $sourceKey;
            if (\str_contains($sourceKey, '/')) {
                $parts = \explode('/', $sourceKey);
                $dataKey = \end($parts);
            }

            // Map output field name if no filter (when filtering, use target key as-is)
            if (null === $fieldFilter) {
                $outputKey = $this->mapFieldName($dataKey);
            }

            $value = $data[$dataKey] ?? $data[$sourceKey] ?? null;
            $contentView = $this->contentResolver->resolve($value, $fieldMetadata, $locale, $attributes);
            $content = $contentView->getContent();

            // Convert null to appropriate empty value based on field type
            if (null === $content) {
                $content = $this->getEmptyValue($fieldMetadata->getType());
            }

            $resolved[$outputKey] = $content;
        }

        return $resolved;
    }

    /**
     * Get the appropriate empty value for a field type.
     */
    private function getEmptyValue(string $fieldType): mixed
    {
        // Types that should return empty array when null
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

        // Default to empty string for text types
        return '';
    }

    /**
     * Map internal field names to expected output format.
     */
    private function mapFieldName(string $fieldName): string
    {
        // Map internal field names to expected output field names
        return match ($fieldName) {
            'image' => 'images',
            'excerptCategories' => 'categories',
            'excerptTags' => 'tags',
            'excerptAudienceTargetGroups' => 'audience_targeting_groups',
            'excerptSegment' => 'segments',
            'seoNoIndex' => 'noIndex',
            'seoNoFollow' => 'noFollow',
            'seoHideInSitemap' => 'hideInSitemap',
            default => $fieldName,
        };
    }

    private function addToReferenceStore(string $id, string $resourceKey): void
    {
        $this->referenceStore->add($id, $resourceKey);
    }
}
