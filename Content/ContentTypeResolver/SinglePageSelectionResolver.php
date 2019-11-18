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

class SinglePageSelectionResolver implements ContentTypeResolverInterface
{
    public static function getContentType(): string
    {
        return 'single_page_selection';
    }

    /**
     * @var PageSelectionResolver
     */
    private $pageSelectionResolver;

    public function __construct(PageSelectionResolver $pageSelectionResolver)
    {
        $this->pageSelectionResolver = $pageSelectionResolver;
    }

    public function resolve($data, PropertyInterface $property, string $locale, array $attributes = []): ContentView
    {
        if (null === $data) {
            return new ContentView([]);
        }

        $content = $this->pageSelectionResolver->resolve([$data], $property, $locale, $attributes);

        return new ContentView($content->getContent()[0] ?? null, $content->getView());
    }
}
