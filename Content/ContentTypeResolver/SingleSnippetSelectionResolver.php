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
use Sulu\Component\Content\Compat\PropertyInterface;

class SingleSnippetSelectionResolver implements ContentTypeResolverInterface
{
    /**
     * @var ContentTypeResolverInterface
     */
    private $snippetSelectionResolver;

    public function __construct(ContentTypeResolverInterface $snippetSelectionResolver)
    {
        $this->snippetSelectionResolver = $snippetSelectionResolver;
    }

    public static function getContentType(): string
    {
        return 'single_snippet_selection';
    }

    /**
     * {@inheritdoc}
     */
    public function resolve($data, PropertyInterface $property, string $locale, array $attributes = []): ContentView
    {
        $snippetId = $data ?: null;

        $contentView = $this->snippetSelectionResolver->resolve(
            $snippetId ? [$snippetId] : null,
            $property,
            $locale,
            $attributes
        );
        $content = $contentView->getContent();
        $view = $contentView->getView();

        return new ContentView(
            $content[0] ?? null,
            ['id' => $view['ids'][0] ?? null]
        );
    }
}
