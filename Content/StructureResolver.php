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
use Sulu\Bundle\WebsiteBundle\ReferenceStore\ReferenceStoreNotExistsException;
use Sulu\Bundle\WebsiteBundle\ReferenceStore\ReferenceStorePoolInterface;
use Sulu\Component\Content\Compat\PropertyInterface;
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
        $requestedStructure = $structure;
        $targetStructure = $this->getTargetStructure($requestedStructure);

        $data = $this->getStructureData($targetStructure, $requestedStructure);

        if ($includeExtension) {
            $data['extension'] = $this->resolveExtensionData(
                $this->getExtensionData($targetStructure),
                $locale,
                ['webspaceKey' => $targetStructure->getWebspaceKey()]
            );
        }

        foreach ($this->getProperties($targetStructure, $requestedStructure) as $property) {
            $contentView = $this->contentResolver->resolve(
                $property->getValue(),
                $property,
                $locale,
                ['webspaceKey' => $property->getStructure()->getWebspaceKey()]
            );

            $data['content'][$property->getName()] = $contentView->getContent();
            $data['view'][$property->getName()] = $contentView->getView();
        }

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
        $requestedStructure = $structure;
        $targetStructure = $this->getTargetStructure($requestedStructure);

        $data = $this->getStructureData($targetStructure, $requestedStructure);

        $unresolvedExtensionData = $this->getExtensionData($targetStructure);

        $attributes = ['webspaceKey' => $targetStructure->getWebspaceKey()];
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
                $property = $this->getProperty($sourceProperty, $targetStructure, $requestedStructure);

                if (null === $property) {
                    continue;
                }

                $contentView = $this->resolveProperty(
                    $property->getStructure(),
                    $sourceProperty,
                    $locale,
                    ['webspaceKey' => $property->getStructure()->getWebspaceKey()],
                    $property->getValue()
                );
            }

            $data['content'][$targetProperty] = $contentView->getContent();
            $data['view'][$targetProperty] = $contentView->getView();
        }

        return $data;
    }

    /**
     * @param StructureBridge $targetStructure
     * @param StructureBridge $requestedStructure
     */
    private function getProperty(
        string $name,
        StructureInterface $targetStructure,
        StructureInterface $requestedStructure
    ): ?PropertyInterface {
        if ('title' === $name && $requestedStructure->hasProperty('title')) {
            return $requestedStructure->getProperty('title');
        }

        if ($targetStructure->hasProperty($name)) {
            return $targetStructure->getProperty($name);
        }

        return null;
    }

    /**
     * @param StructureBridge $targetStructure
     * @param StructureBridge $requestedStructure
     *
     * @return array<string, PropertyInterface>
     */
    private function getProperties(StructureInterface $targetStructure, StructureInterface $requestedStructure): array
    {
        $properties = [];

        foreach ($targetStructure->getProperties(true) as $property) {
            $property = $this->getProperty(
                $property->getName(),
                $targetStructure,
                $requestedStructure
            );

            if (null !== $property) {
                $properties[$property->getName()] = $property;
            }
        }

        return $properties;
    }

    /**
     * @param StructureBridge $targetStructure
     * @param StructureBridge $requestedStructure
     *
     * @return mixed[]
     */
    private function getStructureData(
        StructureInterface $targetStructure,
        StructureInterface $requestedStructure
    ): array {
        $targetDocument = $targetStructure->getDocument();
        $requestedDocument = $requestedStructure->getDocument();

        /** @var string|null $templateKey */
        $templateKey = null;
        if (method_exists($targetDocument, 'getStructureType')) {
            $templateKey = $targetDocument->getStructureType();
        }

        /** @var int|null $author */
        $author = null;
        if (method_exists($targetDocument, 'getAuthor')) {
            $author = $targetDocument->getAuthor();
        }

        /** @var \DateTimeInterface|null $authored */
        $authored = null;
        if (method_exists($targetDocument, 'getAuthored')) {
            /** @var \DateTimeInterface|null $authored typehint in sulu is wrong */
            $authored = $targetDocument->getAuthored();
        }

        /** @var int|null $changer */
        $changer = null;
        if (method_exists($requestedDocument, 'getChanger')) {
            $changer = $requestedDocument->getChanger();
        }

        /** @var \DateTimeInterface|null $changed */
        $changed = null;
        if (method_exists($requestedDocument, 'getChanged')) {
            $changed = $requestedDocument->getChanged();
        }

        /** @var int|null $creator */
        $creator = null;
        if (method_exists($requestedDocument, 'getCreator')) {
            $creator = $requestedDocument->getCreator();
        }

        /** @var \DateTimeInterface|null $created */
        $created = null;
        if (method_exists($requestedDocument, 'getCreated')) {
            $created = $requestedDocument->getCreated();
        }

        $templateType = $this->getTemplateType($targetStructure, $targetDocument);

        $this->addToReferenceStore($targetStructure->getUuid(), $templateType);
        $this->addToReferenceStore(
            $requestedStructure->getUuid(),
            $this->getTemplateType($requestedStructure, $requestedDocument)
        );

        return [
            'id' => $requestedStructure->getUuid(),
            'nodeType' => $requestedStructure->getNodeType(),
            'type' => $templateType,
            'template' => $templateKey,
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
            $templateType = $this->documentInspector->getMetadata($document)->getAlias();

            if ('home' === $templateType) {
                return 'page';
            }

            return $templateType;
        }

        return 'unknown';
    }

    /**
     * @param StructureBridge $structure
     *
     * @return StructureBridge
     */
    private function getTargetStructure(StructureInterface $structure): StructureInterface
    {
        $document = $structure->getDocument();
        if (!$document instanceof RedirectTypeBehavior) {
            return $structure;
        }

        while ($document instanceof RedirectTypeBehavior && RedirectType::INTERNAL === $document->getRedirectType()) {
            $redirectTargetDocument = $document->getRedirectTarget();

            if ($redirectTargetDocument instanceof StructureBehavior) {
                $document = $redirectTargetDocument;
            }
        }

        if ($document !== $structure->getDocument() && $document instanceof StructureBehavior) {
            return $this->documentToStructure($document);
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
