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
use Sulu\Bundle\HeadlessBundle\Content\Serializer\PageSerializerInterface;
use Sulu\Bundle\PageBundle\Content\PageSelectionContainer;
use Sulu\Component\Content\Compat\PropertyInterface;
use Sulu\Component\Content\Compat\PropertyParameter;

class PageSelectionResolver implements ContentTypeResolverInterface
{
    public static function getContentType(): string
    {
        return 'page_selection';
    }

    /**
     * @var PageSerializerInterface
     */
    private $pageSerializer;

    /**
     * @var PageSelectionContainerFactory
     */
    private $pageSelectionContainerFactory;

    public function __construct(
        PageSerializerInterface $pageSerializer,
        PageSelectionContainerFactory $pageSelectionContainerFactory
    ) {
        $this->pageSerializer = $pageSerializer;
        $this->pageSelectionContainerFactory = $pageSelectionContainerFactory;
    }

    public function resolve($data, PropertyInterface $property, string $locale, array $attributes = []): ContentView
    {
        if (null === $data) {
            return new ContentView([]);
        }

        /** @var PropertyParameter[] $params */
        $params = array_merge(
            ['properties' => new PropertyParameter('properties', [], 'collection')],
            $property->getParams()
        );

        // the PageSelectionContainer resolves the data defined in the $params using the default content types
        // for example, this means that the result contains an array of media entities instead of an array of ids
        // TODO: find a solution that returns the unresolved data which can be passed to the resolvers of this bundle
        /** @var mixed[] $pagesData */
        $pagesData = $this->pageSelectionContainerFactory->createContainer(
            $data,
            $params,
            $attributes['webspaceKey'] ?? null,
            $locale
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
