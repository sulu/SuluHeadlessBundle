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

use Sulu\Bundle\DocumentManagerBundle\Bridge\DocumentInspector;
use Sulu\Bundle\PageBundle\Document\BasePageDocument;
use Sulu\Bundle\PageBundle\Preview\PageRouteDefaultsProvider;
use Sulu\Bundle\WebsiteBundle\ReferenceStore\ReferenceStoreNotExistsException;
use Sulu\Bundle\WebsiteBundle\ReferenceStore\ReferenceStorePoolInterface;
use Sulu\Component\Content\Compat\Structure\StructureBridge;
use Sulu\Component\Content\Compat\StructureInterface;
use Sulu\Component\Content\Compat\StructureManagerInterface;
use Sulu\Component\Content\Document\Behavior\RedirectTypeBehavior;
use Sulu\Component\Content\Document\Behavior\StructureBehavior;
use Sulu\Component\Content\Document\Extension\ExtensionContainer;
use Sulu\Component\Content\Document\RedirectType;
use Sulu\Component\Content\Metadata\StructureMetadata;

class StructureResolver implements StructureResolverInterface
{
    /**
     * @var ContentResolverInterface
     */
    private $contentResolver;

    /**
     * @var StructureManagerInterface
     */
    private $structureManager;

    /**
     * @var DocumentInspector
     */
    private $documentInspector;

    /**
     * @var ReferenceStorePoolInterface
     */
    private $referenceStorePool;

    public function __construct(
        ContentResolverInterface $contentResolver,
        StructureManagerInterface $structureManager,
        DocumentInspector $documentInspector,
        ReferenceStorePoolInterface $referenceStorePool
    ) {
        $this->contentResolver = $contentResolver;
        $this->structureManager = $structureManager;
        $this->documentInspector = $documentInspector;
        $this->referenceStorePool = $referenceStorePool;
    }

    /**
     * @param StructureBridge $structure
     */
    public function resolve(
        StructureInterface $structure,
        string $locale,
        bool $includeExtension = true
    ): array {
        $originalStructure = $structure;
        $structure = $this->getMostSpecificStructure($originalStructure);

        $data = $this->getStructureData($structure, $originalStructure);

        if ($includeExtension) {
            $data['extension'] = $this->resolveExtensionData(
                $this->getExtensionData($structure),
                $locale,
                ['webspaceKey' => $structure->getWebspaceKey()]
            );
        }

        foreach ($structure->getProperties(true) as $property) {
            $contentView = $this->contentResolver->resolve(
                $property->getValue(),
                $property,
                $locale,
                ['webspaceKey' => $structure->getWebspaceKey()]
            );

            $data['content'][$property->getName()] = $contentView->getContent();
            $data['view'][$property->getName()] = $contentView->getView();
        }

        $titleProperty = $originalStructure->getProperty('title');

        $contentView = $this->contentResolver->resolve(
            $titleProperty->getValue(),
            $titleProperty,
            $locale,
            ['webspaceKey' => $originalStructure->getWebspaceKey()]
        );

        $data['content'][$titleProperty->getName()] = $contentView->getContent();
        $data['view'][$titleProperty->getName()] = $contentView->getView();

        return $data;
    }

    /**
     * @param StructureBridge $structure
     */
    public function resolveProperties(
        StructureInterface $structure,
        array $propertyMap,
        string $locale,
        bool $includeExtension = false
    ): array {
        $originalStructure = $structure;
        $structure = $this->getMostSpecificStructure($originalStructure);

        $data = $this->getStructureData($structure, $originalStructure);

        $unresolvedExtensionData = $this->getExtensionData($structure);

        $attributes = ['webspaceKey' => $structure->getWebspaceKey()];
        $excerptStructure = $this->structureManager->getStructure('excerpt');

        if ($includeExtension) {
            $data['extension'] = $this->resolveExtensionData($unresolvedExtensionData, $locale, $attributes);
        }

        foreach ($propertyMap as $targetProperty => $sourceProperty) {
            if (!\is_string($targetProperty)) {
                $targetProperty = $sourceProperty;
            }

            // the '.' is used to separate the extension name from the property name.
            if (false !== strpos($sourceProperty, '.')) {
                [$extensionName, $propertyName] = explode('.', $sourceProperty);

                if (!isset($unresolvedExtensionData[$extensionName][$propertyName])) {
                    continue;
                }

                $contentView = new ContentView($unresolvedExtensionData[$extensionName][$propertyName]);
                if ('excerpt' === $extensionName) {
                    $contentView = $this->resolveProperty(
                        $excerptStructure,
                        $propertyName,
                        $locale,
                        $attributes,
                        $contentView->getContent()
                    );
                }
            } else {
                $propertyStructure = $structure;
                if ('title' === $sourceProperty) {
                    $propertyStructure = $originalStructure;
                }

                if (!$propertyStructure->hasProperty($sourceProperty)) {
                    continue;
                }

                $property = $propertyStructure->getProperty($sourceProperty);

                $contentView = $this->resolveProperty(
                    $propertyStructure,
                    $sourceProperty,
                    $locale,
                    $attributes,
                    $property->getValue()
                );
            }

            $data['content'][$targetProperty] = $contentView->getContent();
            $data['view'][$targetProperty] = $contentView->getView();
        }

        return $data;
    }

    /**
     * @param StructureBridge $structure
     * @param StructureBridge $originalStructure
     *
     * @return mixed[]
     */
    private function getStructureData(StructureInterface $structure, StructureInterface $originalStructure): array
    {
        $document = $structure->getDocument();
        $originalDocument = $originalStructure->getDocument();

        /** @var string|null $structureType */
        $structureType = null;
        if (method_exists($document, 'getStructureType')) {
            $structureType = $document->getStructureType();
        }

        /** @var int|null $author */
        $author = null;
        if (method_exists($document, 'getAuthor')) {
            $author = $document->getAuthor();
        }

        /** @var \DateTimeInterface|null $authored */
        $authored = null;
        if (method_exists($document, 'getAuthored')) {
            /** @var \DateTimeInterface|null $authored typehint in sulu is wrong */
            $authored = $document->getAuthored();
        }

        /** @var int|null $changer */
        $changer = null;
        if (method_exists($originalDocument, 'getChanger')) {
            $changer = $originalDocument->getChanger();
        }

        /** @var \DateTimeInterface|null $changed */
        $changed = null;
        if (method_exists($originalDocument, 'getChanged')) {
            $changed = $originalDocument->getChanged();
        }

        /** @var int|null $creator */
        $creator = null;
        if (method_exists($originalDocument, 'getCreator')) {
            $creator = $originalDocument->getCreator();
        }

        /** @var \DateTimeInterface|null $created */
        $created = null;
        if (method_exists($originalDocument, 'getCreated')) {
            $created = $originalDocument->getCreated();
        }

        $type = $this->getTemplateType($structure, $document);

        $this->addToReferenceStore($structure->getUuid(), $type);
        $this->addToReferenceStore(
            $originalStructure->getUuid(),
            $this->getTemplateType($originalStructure, $originalDocument)
        );

        return [
            'id' => $originalStructure->getUuid(),
            'nodeType' => $originalStructure->getNodeType(),
            'type' => $type,
            'template' => $structureType,
            'content' => [],
            'view' => [],
            'author' => $author,
            'authored' => $authored ? $authored->format(\DateTimeImmutable::ISO8601) : null,
            'changer' => $changer,
            'changed' => $changed ? $changed->format(\DateTimeImmutable::ISO8601) : null,
            'creator' => $creator,
            'created' => $created ? $created->format(\DateTimeImmutable::ISO8601) : null,
        ];
    }

    /**
     * @param StructureBridge $structure
     *
     * @return mixed[]
     */
    private function getExtensionData(StructureInterface $structure): array
    {
        /** @var BasePageDocument $document */
        $document = $structure->getDocument();

        /** @var ExtensionContainer|mixed[] $extensionData */
        $extensionData = [];
        if (method_exists($document, 'getExtensionsData')) {
            $extensionData = $document->getExtensionsData();
        }

        if ($extensionData instanceof ExtensionContainer) {
            $extensionData = $extensionData->toArray();
        }

        return $extensionData;
    }

    /**
     * @param mixed[] $data
     * @param mixed[] $attributes
     *
     * @return mixed[]
     */
    private function resolveExtensionData(array $data, string $locale, array $attributes): array
    {
        $excerptStructure = $this->structureManager->getStructure('excerpt');

        $unresolvedExcerptData = $data['excerpt'] ?? [];

        $resolvedExcerptData = [];
        foreach ($excerptStructure->getProperties(true) as $property) {
            $resolvedExcerptData[$property->getName()] = $this->resolveProperty(
                $excerptStructure,
                $property->getName(),
                $locale,
                $attributes,
                $unresolvedExcerptData[$property->getName()] ?? null
            )->getContent();
        }

        $data['excerpt'] = $resolvedExcerptData;

        return $data;
    }

    /**
     * @param mixed $value
     * @param mixed[] $attributes
     */
    private function resolveProperty(
        StructureInterface $structure,
        string $name,
        string $locale,
        array $attributes,
        $value
    ): ContentView {
        $property = $structure->getProperty($name);
        $property->setValue($value);

        return $this->contentResolver->resolve(
            $value,
            $property,
            $locale,
            $attributes
        );
    }

    private function addToReferenceStore(string $uuid, string $alias): void
    {
        if ('page' === $alias) {
            // unfortunately the reference store for pages was not adjusted and still uses content as alias
            $alias = 'content';
        }

        try {
            $referenceStore = $this->referenceStorePool->getStore($alias);
        } catch (ReferenceStoreNotExistsException $e) {
            // @ignoreException do nothing when reference store was not found

            return;
        }

        $referenceStore->add($uuid);
    }

    private function getTemplateType(StructureInterface $structure, object $document): string
    {
        $structureContent = null;

        if (method_exists($structure, 'getContent')) {
            $structureContent = $structure->getContent();
        }

        if (\is_object($structureContent) && method_exists($structureContent, 'getTemplateType')) {
            // determine type for structure that is implemented based on the SuluContentBundle
            return $structureContent->getTemplateType();
        }

        if ($document instanceof StructureBehavior) {
            // determine type for structure that is implemented in the SuluPageBundle or the SuluArticleBundle
            $type = $this->documentInspector->getMetadata($document)->getAlias();

            if ('home' === $type) {
                return 'page';
            }

            return $type;
        }

        return 'unknown';
    }

    /**
     * @param StructureBridge $structure
     *
     * @return StructureBridge
     */
    private function getMostSpecificStructure(StructureInterface $structure): StructureInterface
    {
        $document = $structure->getDocument();

        if ($document instanceof RedirectTypeBehavior
            && RedirectType::INTERNAL === $document->getRedirectType()) {
            $redirectTargetDocument = $document->getRedirectTarget();

            if ($redirectTargetDocument instanceof StructureBehavior) {
                return $this->documentToStructure($redirectTargetDocument);
            }
        }

        return $structure;
    }

    /**
     * @see PageRouteDefaultsProvider::documentToStructure()
     *
     * @return StructureBridge
     */
    private function documentToStructure(StructureBehavior $document): StructureInterface
    {
        /** @var StructureMetadata $structure */
        $structure = $this->documentInspector->getStructureMetadata($document);
        $documentAlias = $this->documentInspector->getMetadata($document)->getAlias();

        /** @var StructureBridge $structureBridge */
        $structureBridge = $this->structureManager->wrapStructure($documentAlias, $structure);
        $structureBridge->setDocument($document);

        return $structureBridge;
    }
}
