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

namespace Sulu\Bundle\HeadlessBundle\Content;

use Sulu\Content\Domain\Model\DimensionContentInterface;

interface StructureResolverInterface
{
    /**
     * Resolve a dimension content to a JSON-serializable array.
     *
     * @param array<string, string>|null $properties Optional property map to resolve only specific properties
     *
     * @return array<string, mixed>
     */
    public function resolve(
        DimensionContentInterface $dimensionContent,
        string $locale,
        bool $includeExtension = true,
        ?array $properties = null,
    ): array;

    /**
     * Resolve only specific properties from the dimension content.
     *
     * @param array<string, string> $propertyMap Map of target property names to source property names
     *
     * @return array<string, mixed>
     */
    public function resolveProperties(
        DimensionContentInterface $dimensionContent,
        array $propertyMap,
        string $locale,
        bool $includeExtension = false,
    ): array;
}
