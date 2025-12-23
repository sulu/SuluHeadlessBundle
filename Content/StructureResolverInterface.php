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
     * @return array<string, mixed>
     */
    public function resolve(
        DimensionContentInterface $dimensionContent,
        string $locale,
        bool $includeExtension = true,
    ): array;

    /**
     * @param array<string, string> $propertyMap
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
