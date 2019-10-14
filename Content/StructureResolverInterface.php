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

use Sulu\Component\Content\Compat\StructureInterface;

interface StructureResolverInterface
{
    /**
     * @return mixed[]
     */
    public function resolve(StructureInterface $structure, string $locale): array;
}
