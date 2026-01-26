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

class SingleMediaSelectionResolver implements ContentTypeResolverInterface
{
    public static function getContentType(): string
    {
        return 'single_media_selection';
    }

    public function __construct(
        private ContentTypeResolverInterface $mediaSelectionResolver,
    ) {
    }

    public function resolve(mixed $data, FieldMetadata $fieldMetadata, string $locale, array $attributes = []): ContentView
    {
        if (empty($data) || !\is_array($data)) {
            return new ContentView(null, ['id' => null]);
        }

        $ids = \array_key_exists('id', $data) && \is_numeric($data['id']) ? [(int) $data['id']] : [];
        $content = $this->mediaSelectionResolver->resolve(['ids' => $ids], $fieldMetadata, $locale, $attributes);
        $resolvedContent = $content->getContent();

        return new ContentView(\is_array($resolvedContent) ? ($resolvedContent[0] ?? null) : null, \array_merge(['id' => null], $data));
    }
}
