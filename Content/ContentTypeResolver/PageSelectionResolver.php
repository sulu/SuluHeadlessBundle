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

use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\PageSerializer;
use Sulu\Component\Content\Compat\PropertyInterface;
use Sulu\Component\Content\Compat\PropertyParameter;

class PageSelectionResolver implements ContentTypeResolverInterface
{
    public static function getContentType(): string
    {
        return 'page_selection';
    }

    /**
     * @var PageSerializer
     */
    private $pageSerializer;

    /**
     * @var PageSelectionContainerFactory
     */
    private $pageSelectionContainerFactory;

    public function __construct(
        PageSerializer $pageSerializer,
        PageSelectionContainerFactory $pageSelectionContainerFactory
    ) {
        $this->pageSerializer = $pageSerializer;
        $this->pageSelectionContainerFactory = $pageSelectionContainerFactory;
    }

    public function resolve($data, PropertyInterface $property, string $locale, array $attributes = []): ContentView
    {
        /** @var PropertyParameter[] $params */
        $params = array_merge(
            ['properties' => new PropertyParameter('properties', [], 'collection')],
            $property->getParams()
        );

        /** @var mixed[] $pagesData */
        $pagesData = $this->pageSelectionContainerFactory->createContainer(
            $data,
            $params,
            $property->getStructure()->getWebspaceKey(),
            $property->getStructure()->getLanguageCode()
        )->getData();

        /** @var PropertyParameter[] $propertyParameters */
        $propertyParameters = $params['properties']->getValue();

        $pages = [];
        foreach ($pagesData as $pageData) {
            $pages[] = $this->pageSerializer->serialize($pageData, $propertyParameters);
        }

        return new ContentView($pages, $data);
    }
}
