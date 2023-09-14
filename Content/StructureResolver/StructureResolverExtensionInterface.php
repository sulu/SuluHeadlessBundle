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

namespace Sulu\Bundle\HeadlessBundle\Content\StructureResolver;

use Sulu\Component\Content\Compat\StructureInterface;

interface StructureResolverExtensionInterface
{
    public function getKey(): string;

    public function resolve(
        StructureInterface $requestedStructure,
        string $locale
    ): mixed;
}
