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

use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;

class SingleCollectionSelectionResolver implements ContentTypeResolverInterface
{
    public static function getContentType(): string
    {
        return 'single_collection_selection';
    }

    public function __construct(
        private ContentTypeResolverInterface $collectionSelectionResolver,
    ) {
    }

    public function resolve(mixed $data, FieldMetadata $fieldMetadata, string $locale, array $attributes = []): ContentView
    {
        $id = $data;

        if (!\is_numeric($id)) {
            return new ContentView(null, ['id' => null]);
        }

        $content = $this->collectionSelectionResolver->resolve([(int) $id], $fieldMetadata, $locale, $attributes);

        /** @var mixed[]|null $contentData */
        $contentData = $content->getContent();

        return new ContentView($contentData[0] ?? null, ['id' => $id]);
    }
}
