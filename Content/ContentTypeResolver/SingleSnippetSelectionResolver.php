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

class SingleSnippetSelectionResolver implements ContentTypeResolverInterface
{
    public static function getContentType(): string
    {
        return 'single_snippet_selection';
    }

    public function __construct(
        private ContentTypeResolverInterface $snippetSelectionResolver,
    ) {
    }

    public function resolve(mixed $data, FieldMetadata $fieldMetadata, string $locale, array $attributes = []): ContentView
    {
        $snippetId = $data ?: null;

        $contentView = $this->snippetSelectionResolver->resolve(
            \is_string($snippetId) ? [$snippetId] : null,
            $fieldMetadata,
            $locale,
            $attributes,
        );
        $content = $contentView->getContent();
        $view = $contentView->getView();
        $viewIds = $view['ids'] ?? [];

        return new ContentView(
            \is_array($content) ? ($content[0] ?? null) : null,
            ['id' => \is_array($viewIds) ? ($viewIds[0] ?? null) : null],
        );
    }
}
