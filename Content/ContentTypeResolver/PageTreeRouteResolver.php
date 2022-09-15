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

class PageTreeRouteResolver implements ContentTypeResolverInterface
{
    public static function getContentType(): string
    {
        return 'page_tree_route';
    }

    public function resolve($data, PropertyInterface $property, string $locale, array $attributes = []): ContentView
    {
        if (!\is_array($data)) {
            return new ContentView($data, []);
        }

        return new ContentView($data['path'] ?? null, $data);
    }
}
