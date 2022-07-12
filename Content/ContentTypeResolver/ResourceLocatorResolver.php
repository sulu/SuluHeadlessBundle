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

class ResourceLocatorResolver implements ContentTypeResolverInterface
{
    public static function getContentType(): string
    {
        return 'resource_locator';
    }

    public function resolve($data, PropertyInterface $property, string $locale, array $attributes = []): ContentView
    {
        $resourceLocator = $data;
        $structure = $property->getStructure();

        // The getResourceLocator method returns the target url for pages of type internal and external link
        if (\method_exists($structure, 'getResourceLocator')) {
            $resourceLocator = $structure->getResourceLocator();
        }

        return new ContentView($resourceLocator);
    }
}
