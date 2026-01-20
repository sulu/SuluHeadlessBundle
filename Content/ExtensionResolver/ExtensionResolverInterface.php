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

namespace Sulu\Bundle\HeadlessBundle\Content\ExtensionResolver;

use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Content\Domain\Model\DimensionContentInterface;

interface ExtensionResolverInterface
{
    public function getPrefix(): string;

    /**
     * @param array<string, string> $properties
     * @param array<string, mixed> $attributes
     */
    public function resolve(
        DimensionContentInterface $dimensionContent,
        array $properties,
        string $locale,
        array $attributes = [],
    ): ContentView;
}
