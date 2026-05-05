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

use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TypedFormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderInterface;
use Sulu\Bundle\HeadlessBundle\Content\ExtensionResolver\ExtensionResolverProvider;
use Sulu\Bundle\HttpCacheBundle\ReferenceStore\ReferenceStoreInterface;
use Sulu\Component\Persistence\Model\AuditableInterface;
use Sulu\Content\Application\ContentDataMapper\DataMapper\TemplateDataMapper;
use Sulu\Content\Domain\Model\AuthorInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Domain\Model\ExcerptInterface;
use Sulu\Content\Domain\Model\LinkInterface;
use Sulu\Content\Domain\Model\RoutableInterface;
use Sulu\Content\Domain\Model\SeoInterface;
use Sulu\Content\Domain\Model\ShadowInterface;
use Sulu\Content\Domain\Model\TaxonomyInterface;
use Sulu\Content\Domain\Model\TemplateInterface;
use Sulu\Page\Domain\Model\PageInterface;

class StructureResolver implements StructureResolverInterface
{
    public function __construct(
        private MetadataProviderInterface $formMetadataProvider,
        private ContentResolverInterface $contentResolver,
        private ReferenceStoreInterface $referenceStore,
        private ExtensionResolverProvider $extensionResolverProvider,
    ) {
    }

    /**
     * @param array<string, string>|null $properties
     */
    public function resolve(
        DimensionContentInterface $dimensionContent,
        string $locale,
        bool $includeExtension = true,
        ?array $properties = null,
    ): array {
        $resource = $dimensionContent->getResource();
        /** @var string $resourceId */
        $resourceId = $resource->getId();
        $resourceKey = $dimensionContent::getResourceKey();

        $this->referenceStore->add($resourceId, $resourceKey);

        $attributes = $this->buildAttributes($dimensionContent);

        $data = [
            'id' => $resourceId,
            'type' => $dimensionContent instanceof TemplateInterface ? $dimensionContent::getTemplateType() : null,
        ];

        if ($dimensionContent instanceof LinkInterface) {
            $linkData = $dimensionContent->getLinkData();
            $data['linkType'] = null !== $linkData ? ($linkData['provider'] ?? null) : null;
        }

        if ($dimensionContent instanceof TemplateInterface) {
            $templateKey = $dimensionContent->getTemplateKey();
            $data['template'] = $templateKey;

            $data['content'] = [];
            $data['view'] = [];
            if (null !== $templateKey) {
                $contentView = $this->resolveTemplateContent(
                    $dimensionContent,
                    $templateKey,
                    $locale,
                    $attributes,
                    $properties,
                );

                $data['content'] = $contentView->getContent();
                $data['view'] = $contentView->getView();
            }
        }

        if ($includeExtension) {
            $data['extension'] = $this->resolveExtensions(
                $dimensionContent,
                $locale,
                $attributes,
                $properties
            );
        }

        if ($dimensionContent instanceof AuthorInterface) {
            $author = $dimensionContent->getAuthor();
            $authored = $dimensionContent->getAuthored();

            $data['author'] = $author?->getId();
            $data['authored'] = $authored?->format(\DateTimeImmutable::ATOM);
        }

        if ($resource instanceof AuditableInterface) {
            $data['changer'] = $resource->getChanger()?->getId();
            $data['changed'] = $resource->getChanged()->format(\DateTimeImmutable::ATOM);
            $data['creator'] = $resource->getCreator()?->getId();
            $data['created'] = $resource->getCreated()->format(\DateTimeImmutable::ATOM);
        }

        return $data;
    }

    /**
     * @param array<string, string> $propertyMap
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
     * @param array<string, mixed> $attributes
     * @param array<string, string>|null $properties
     */
    private function resolveTemplateContent(
        TemplateInterface $dimensionContent,
        string $templateKey,
        string $locale,
        array $attributes,
        ?array $properties = null,
    ): ContentView {
        /** @var TypedFormMetadata $typedFormMetadata */
        $typedFormMetadata = $this->formMetadataProvider->getMetadata($dimensionContent::getTemplateType(), $locale, []);
        $formMetadata = $typedFormMetadata->getForms()[$templateKey] ?? null;

        $content = [];
        $view = [];
        if (!$formMetadata) {
            return new ContentView($content, $view);
        }

        $fieldMetadataList = $formMetadata->getFlatFieldMetadata();
        $templateData = $dimensionContent->getTemplateData();
        $templateData = $this->fillRouteFieldsFromRoute($dimensionContent, $templateData, $fieldMetadataList);

        if (null !== $properties) {
            $filteredFieldMetadata = [];
            $filteredTemplateData = [];
            foreach ($properties as $targetKey => $sourceKey) {
                $isExtensionProperty = false;
                foreach ($this->extensionResolverProvider->getResolvers() as $resolver) {
                    if (\str_starts_with($sourceKey, $resolver->getPrefix())) {
                        $isExtensionProperty = true;
                        break;
                    }
                }

                if ($isExtensionProperty) {
                    continue;
                }

                if (\array_key_exists($sourceKey, $fieldMetadataList)) {
                    $filteredFieldMetadata[$targetKey] = $fieldMetadataList[$sourceKey];
                }
                if (\array_key_exists($sourceKey, $templateData)) {
                    $filteredTemplateData[$targetKey] = $templateData[$sourceKey];
                }
            }
            $fieldMetadataList = $filteredFieldMetadata;
            $templateData = $filteredTemplateData;

            if ($dimensionContent instanceof DimensionContentInterface) {
                $this->resolveExtensionProperties($dimensionContent, $properties, $locale, $attributes, $content, $view);
            }
        }

        foreach ($fieldMetadataList as $fieldName => $fieldMetadata) {
            $value = $templateData[$fieldName] ?? null;
            $contentView = $this->contentResolver->resolve($value, $fieldMetadata, $locale, $attributes);
            $content[$fieldName] = $contentView->getContent();
            $view[$fieldName] = $contentView->getView();
        }

        return new ContentView($content, $view);
    }

    /**
     * @param array<string, string> $properties
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $content
     * @param array<string, mixed> $view
     */
    private function resolveExtensionProperties(
        DimensionContentInterface $dimensionContent,
        array $properties,
        string $locale,
        array $attributes,
        array &$content,
        array &$view,
    ): void {
        foreach ($this->extensionResolverProvider->getResolvers() as $resolver) {
            if ($this->extensionResolverProvider->hasPropertiesWithPrefix($properties, $resolver->getPrefix())) {
                $extensionView = $resolver->resolve($dimensionContent, $properties, $locale, $attributes);
                $extensionContent = $extensionView->getContent();
                \assert(\is_array($extensionContent));
                $content = \array_merge($content, $extensionContent);
                $view = \array_merge($view, $extensionView->getView());
            }
        }
    }

    /**
     * @param array<string, mixed> $templateData
     * @param array<string, FieldMetadata> $fieldMetadataList
     *
     * @return array<string, mixed>
     */
    private function fillRouteFieldsFromRoute(
        TemplateInterface $dimensionContent,
        array $templateData,
        array $fieldMetadataList,
    ): array {
        if (!$dimensionContent instanceof RoutableInterface) {
            return $templateData;
        }

        $route = $dimensionContent->getRoute();
        if (null === $route) {
            return $templateData;
        }

        foreach ($fieldMetadataList as $name => $field) {
            if (!$field->hasTag(TemplateDataMapper::SKIP_TAG)) {
                continue;
            }

            $type = $field->getType();
            if ('route' === $type) {
                $templateData[$name] = $route->getSlug();
            } elseif ('page_tree_route' === $type) {
                $parentRoute = $route->getParentRoute();
                if (null === $parentRoute) {
                    continue;
                }
                $parentSlug = $parentRoute->getSlug();
                $slug = $route->getSlug();
                $suffix = \str_starts_with($slug, $parentSlug)
                    ? \substr($slug, \strlen($parentSlug))
                    : '';
                $templateData[$name] = [
                    'page' => [
                        'uuid' => $parentRoute->getResourceId(),
                        'path' => $parentSlug,
                    ],
                    'suffix' => $suffix,
                ];
            }
        }

        return $templateData;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAttributes(DimensionContentInterface $dimensionContent): array
    {
        $resource = $dimensionContent->getResource();

        $attributes = [
            'uuid' => $resource->getId(),
        ];

        if ($resource instanceof PageInterface) {
            $attributes['webspaceKey'] = $resource->getWebspaceKey();
        }

        $attributes['isShadow'] = false;
        $attributes['shadowLocale'] = null;
        if ($dimensionContent instanceof ShadowInterface) {
            $shadowLocale = $dimensionContent->getShadowLocale();
            $attributes['isShadow'] = null !== $shadowLocale;
            $attributes['shadowLocale'] = $shadowLocale;
        }

        return $attributes;
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, string>|null $properties
     *
     * @return array<string, mixed>
     */
    private function resolveExtensions(
        DimensionContentInterface $dimensionContent,
        string $locale,
        array $attributes,
        ?array $properties = null,
    ): array {
        $extensions = [];

        $excerptData = [];

        if ($dimensionContent instanceof ExcerptInterface) {
            $excerptData = $this->resolveExcerptData(
                $dimensionContent,
                $locale,
                $attributes,
                $properties,
            );
        }

        if ($dimensionContent instanceof TaxonomyInterface) {
            $excerptData = \array_merge(
                $excerptData,
                $this->resolveTaxonomyData($dimensionContent, $properties)
            );
        }

        if ([] !== $excerptData) {
            $extensions['excerpt'] = $excerptData;
        }

        if ($dimensionContent instanceof SeoInterface) {
            $extensions['seo'] = $this->resolveSeoData(
                $dimensionContent,
                $locale,
                $attributes,
                $properties,
            );
        }

        return $extensions;
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, string>|null $properties
     *
     * @return array<string, mixed>
     */
    private function resolveExcerptData(
        ExcerptInterface $dimensionContent,
        string $locale,
        array $attributes,
        ?array $properties = null,
    ): array {
        $data = $dimensionContent->getExcerptData();

        $formMetadata = $this->formMetadataProvider->getMetadata(
            'content_excerpt',
            $locale,
            ['instanceOf' => ExcerptInterface::class]
        );

        if (!$formMetadata instanceof FormMetadata) {
            return $data;
        }

        return $this->resolveFormFields($formMetadata, $data, $locale, $attributes, $properties, 'excerpt/');
    }

    /**
     * @param array<string, string>|null $properties
     *
     * @return array<string, mixed>
     */
    private function resolveTaxonomyData(
        TaxonomyInterface $dimensionContent,
        ?array $properties = null,
    ): array {
        $data = [
            'categories' => $dimensionContent->getExcerptCategoryIds(),
            'tags' => $dimensionContent->getExcerptTagNames(),
            'audience_targeting_groups' => $dimensionContent->getExcerptAudienceTargetGroupIds(),
            'segments' => $dimensionContent->getExcerptSegment() ?? [],
        ];

        if (null !== $properties) {
            $filteredData = [];
            foreach ($properties as $targetKey => $sourceKey) {
                if (\array_key_exists($sourceKey, $data)) {
                    $filteredData[$targetKey] = $data[$sourceKey];
                }
            }

            return $filteredData;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, string>|null $properties
     *
     * @return array<string, mixed>
     */
    private function resolveSeoData(
        SeoInterface $dimensionContent,
        string $locale,
        array $attributes,
        ?array $properties = null,
    ): array {
        $booleanFields = [
            'noIndex' => $dimensionContent->getSeoNoIndex(),
            'noFollow' => $dimensionContent->getSeoNoFollow(),
            'hideInSitemap' => $dimensionContent->getSeoHideInSitemap(),
        ];

        $formMetadata = $this->formMetadataProvider->getMetadata(
            'content_seo',
            $locale,
            ['instanceOf' => SeoInterface::class]
        );

        if (!$formMetadata instanceof FormMetadata) {
            return \array_merge($booleanFields, $dimensionContent->getSeoData());
        }

        $resolved = $this->resolveFormFields($formMetadata, $dimensionContent->getSeoData(), $locale, $attributes, $properties, 'seo/');

        return \array_merge($resolved, $booleanFields);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $attributes
     * @param array<string, string>|null $properties
     *
     * @return array<string, mixed>
     */
    private function resolveFormFields(
        FormMetadata $formMetadata,
        array $data,
        string $locale,
        array $attributes,
        ?array $properties = null,
        ?string $stripPrefix = null,
    ): array {
        $fieldMetadataList = $formMetadata->getFlatFieldMetadata();
        $resolved = [];

        $displayOnlyTypes = ['search_result'];

        $fieldsToResolve = [];
        if (null !== $properties) {
            foreach ($properties as $targetKey => $sourceKey) {
                if (\array_key_exists($sourceKey, $fieldMetadataList)) {
                    $fieldsToResolve[$targetKey] = [
                        'metadata' => $fieldMetadataList[$sourceKey],
                        'dataKey' => $sourceKey,
                    ];
                }
            }
        } else {
            foreach ($fieldMetadataList as $fieldName => $fieldMetadata) {
                $outputKey = $fieldName;
                if (null !== $stripPrefix) {
                    if (!\str_starts_with($fieldName, $stripPrefix)) {
                        continue;
                    }
                    $outputKey = \substr($fieldName, \strlen($stripPrefix));
                }
                $fieldsToResolve[$outputKey] = [
                    'metadata' => $fieldMetadata,
                    'dataKey' => $outputKey,
                ];
            }
        }

        foreach ($fieldsToResolve as $outputKey => $fieldInfo) {
            $fieldMetadata = $fieldInfo['metadata'];
            $dataKey = $fieldInfo['dataKey'];

            if (\in_array($fieldMetadata->getType(), $displayOnlyTypes, true)) {
                continue;
            }

            $value = $data[$dataKey] ?? null;
            $contentView = $this->contentResolver->resolve($value, $fieldMetadata, $locale, $attributes);
            $content = $contentView->getContent();

            if (null === $content) {
                $content = $this->getEmptyValue($fieldMetadata->getType());
            }

            $resolved[$outputKey] = $content;
        }

        return $resolved;
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
