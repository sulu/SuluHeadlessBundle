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
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\CategoryBundle\Category\CategoryManagerInterface;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\CategorySerializerInterface;

class SingleCategorySelectionResolver implements ContentTypeResolverInterface
{
    public static function getContentType(): string
    {
        return 'single_category_selection';
    }

    public function __construct(
        private CategoryManagerInterface $categoryManager,
        private CategorySerializerInterface $categorySerializer,
    ) {
    }

    public function resolve(mixed $data, FieldMetadata $fieldMetadata, string $locale, array $attributes = []): ContentView
    {
        if (!\is_numeric($data)) {
            return new ContentView(null, ['id' => null]);
        }

        $category = $this->categoryManager->findById((int) $data);
        $serializationContext = new SerializationContext();
        $serializationContext->setGroups(['partialCategory']);

        $content = $this->categorySerializer->serialize($category, $locale, $serializationContext);

        return new ContentView($content, ['id' => $data]);
    }
}
