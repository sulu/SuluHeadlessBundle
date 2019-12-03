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

use Sulu\Bundle\PageBundle\Document\BasePageDocument;
use Sulu\Component\Content\Compat\Structure\StructureBridge;
use Sulu\Component\Content\Compat\StructureInterface;
use Sulu\Component\Content\Document\Extension\ExtensionContainer;

class StructureResolver implements StructureResolverInterface
{
    /**
     * @var ContentResolverInterface
     */
    private $contentResolver;

    public function __construct(ContentResolverInterface $contentResolver)
    {
        $this->contentResolver = $contentResolver;
    }

    /**
     * @param StructureBridge $structure
     */
    public function resolve(StructureInterface $structure, string $locale): array
    {
        $data = $this->getStructureData($structure);
        $data['extension'] = $this->getExtensionData($structure);

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
        $data = $this->getStructureData($structure);
        $extensionData = $this->getExtensionData($structure);

        if ($includeExtension) {
            $data['extension'] = $extensionData;
        }

        foreach ($propertyMap as $targetProperty => $sourceProperty) {
            if (!\is_string($targetProperty)) {
                $targetProperty = $sourceProperty;
            }

            // the '.' is used to separate the extension name from the property name.
            if (false !== strpos($sourceProperty, '.')) {
                list($extensionName, $propertyName) = explode('.', $sourceProperty);
                $value = $extensionData[$extensionName][$propertyName];
                $contentView = new ContentView($value);
            } else {
                $property = $structure->getProperty($sourceProperty);
                $contentView = $this->contentResolver->resolve(
                    $property->getValue(),
                    $property,
                    $locale,
                    ['webspaceKey' => $structure->getWebspaceKey()]
                );
            }

            $data['content'][$targetProperty] = $contentView->getContent();
            $data['view'][$targetProperty] = $contentView->getView();
        }

        return $data;
    }

    /**
     * @param StructureBridge $structure
     *
     * @return mixed[]
     */
    private function getStructureData(StructureInterface $structure): array
    {
        /** @var BasePageDocument $document */
        $document = $structure->getDocument();

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
        if (method_exists($document, 'getChanger')) {
            $changer = $document->getChanger();
        }

        /** @var \DateTimeInterface|null $changed */
        $changed = null;
        if (method_exists($document, 'getChanged')) {
            $changed = $document->getChanged();
        }

        /** @var int|null $creator */
        $creator = null;
        if (method_exists($document, 'getCreator')) {
            $creator = $document->getCreator();
        }

        /** @var \DateTimeInterface|null $created */
        $created = null;
        if (method_exists($document, 'getCreated')) {
            $created = $document->getCreated();
        }

        $type = 'page';
        if (method_exists($structure, 'getContent') && method_exists($structure->getContent(), 'getTemplateType')) {
            // used for content-bundle to determine current template type
            $type = $structure->getContent()->getTemplateType();
        }

        return [
            'id' => $structure->getUuid(),
            'type' => $type,
            'template' => $document->getStructureType(),
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

        /** @var ExtensionContainer|array $extensionData */
        $extensionData = [];
        if (method_exists($document, 'getExtensionsData')) {
            $extensionData = $document->getExtensionsData();
        }

        if ($extensionData instanceof ExtensionContainer) {
            $extensionData = $extensionData->toArray();
        }

        // unset keys that contain values that need to be resolved before they can be used in the frontend
        // TODO: find a strategy for resolving the values of the extension data using the headless-bundle resolvers
        unset($extensionData['excerpt']['categories']);
        unset($extensionData['excerpt']['tags']);
        unset($extensionData['excerpt']['icon']);
        unset($extensionData['excerpt']['images']);
        unset($extensionData['excerpt']['audience_targeting_groups']);

        return $extensionData;
    }
}
