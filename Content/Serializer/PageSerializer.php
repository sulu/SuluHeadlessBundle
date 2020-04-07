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

namespace Sulu\Bundle\HeadlessBundle\Content\Serializer;

use Sulu\Bundle\HeadlessBundle\Content\ContentResolverInterface;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Component\Content\Compat\PropertyParameter;
use Sulu\Component\Content\Compat\StructureInterface;
use Sulu\Component\Content\Compat\StructureManagerInterface;

class PageSerializer implements PageSerializerInterface
{
    /**
     * @var StructureManagerInterface
     */
    private $structureManager;

    /**
     * @var ContentResolverInterface
     */
    private $contentResolver;

    public function __construct(
        StructureManagerInterface $structureManager,
        ContentResolverInterface $contentResolver
    ) {
        $this->structureManager = $structureManager;
        $this->contentResolver = $contentResolver;
    }

    /**
     * @param mixed[] $data
     * @param PropertyParameter[] $propertyParameters
     *
     * @return mixed[]
     */
    public function serialize(array $data, array $propertyParameters): array
    {
        $structure = $this->structureManager->getStructure($data['template']);
        $excerpt = $this->structureManager->getStructure('excerpt');

        // TODO: this class should use the StructureResolver to resolve the properties of the structure
        foreach ($propertyParameters as $propertyParameter) {
            /** @var string $targetPropertyName */
            $targetPropertyName = $propertyParameter->getName();

            /** @var string $propertyName */
            $propertyName = $propertyParameter->getValue();

            $propertyValue = $data[$targetPropertyName] ?? null;
            if (null === $propertyValue) {
                continue;
            }

            $locale = $data['locale'];
            $webspaceKey = $data['webspaceKey'];

            if (false !== strpos($propertyName, '.')) {
                // the '.' is used to separate the extension from the property name.
                $propertyName = explode('.', $propertyName)[1];

                $contentView = $this->resolveProperty($excerpt, $propertyName, $locale, $webspaceKey, $propertyValue);
                $data[$targetPropertyName] = $contentView->getContent();

                continue;
            }

            $contentView = $this->resolveProperty($structure, $propertyName, $locale, $webspaceKey, $propertyValue);
            $data[$targetPropertyName] = $contentView->getContent();
        }

        return $data;
    }

    /**
     * @param mixed $value
     */
    private function resolveProperty(
        StructureInterface $structure,
        string $name,
        string $locale,
        string $webspaceKey,
        $value
    ): ContentView {
        $property = $structure->getProperty($name);
        $property->setValue($value);

        return $this->contentResolver->resolve(
            $value,
            $property,
            $locale,
            ['webspaceKey' => $webspaceKey]
        );
    }
}
