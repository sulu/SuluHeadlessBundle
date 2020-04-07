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

use JMS\Serializer\SerializationContext;
use Sulu\Bundle\CategoryBundle\Api\Category;
use Sulu\Bundle\CategoryBundle\Category\CategoryManagerInterface;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\CategorySerializerInterface;
use Sulu\Component\Content\Compat\PropertyInterface;

class CategorySelectionResolver implements ContentTypeResolverInterface
{
    public static function getContentType(): string
    {
        return 'category_selection';
    }

    /**
     * @var CategoryManagerInterface
     */
    private $categoryManager;

    /**
     * @var CategorySerializerInterface
     */
    private $categorySerializer;

    public function __construct(
        CategoryManagerInterface $categoryManager,
        CategorySerializerInterface $categorySerializer
    ) {
        $this->categoryManager = $categoryManager;
        $this->categorySerializer = $categorySerializer;
    }

    public function resolve($data, PropertyInterface $property, string $locale, array $attributes = []): ContentView
    {
        if (null === $data) {
            return new ContentView([]);
        }

        if (0 < \count($data) && \array_key_exists(0, $data) && isset($data[0]['id'])) {
            // we need to extract the category ids if they are already loaded
            // this happens for example when resolving a smart-content property that uses the page provider

            $categoryIds = [];
            foreach ($data as $category) {
                $categoryIds[] = $category['id'];
            }

            $data = $categoryIds;
        }

        /** @var Category[] $categories */
        $categories = $this->categoryManager->getApiObjects($this->categoryManager->findByIds($data), $locale);

        return new ContentView($this->resolveApiCategories($categories), $data ?? []);
    }

    /**
     * @param Category[] $categories
     *
     * @return array[]
     */
    private function resolveApiCategories(array $categories): array
    {
        $content = [];
        foreach ($categories as $category) {
            $serializationContext = new SerializationContext();
            $serializationContext->setGroups(['partialCategory']);

            $content[] = $this->categorySerializer->serialize($category, $serializationContext);
        }

        return $content;
    }
}
