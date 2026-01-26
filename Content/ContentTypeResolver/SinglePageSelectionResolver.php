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

class SinglePageSelectionResolver implements ContentTypeResolverInterface
{
    public static function getContentType(): string
    {
        return 'single_page_selection';
    }

    public function __construct(
        private ContentTypeResolverInterface $pageSelectionResolver,
    ) {
    }

    public function resolve(mixed $data, FieldMetadata $fieldMetadata, string $locale, array $attributes = []): ContentView
    {
        if (!\is_string($data) || '' === $data) {
            return new ContentView(null, ['id' => '']);
        }

        $content = $this->pageSelectionResolver->resolve([$data], $fieldMetadata, $locale, $attributes);
        $resolvedContent = $content->getContent();

        return new ContentView(\is_array($resolvedContent) ? ($resolvedContent[0] ?? null) : null, ['id' => $data]);
    }
}
