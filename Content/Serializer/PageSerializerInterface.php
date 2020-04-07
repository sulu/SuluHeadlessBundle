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

namespace Sulu\Bundle\HeadlessBundle\Content\Serializer;

use Sulu\Component\Content\Compat\PropertyParameter;

interface PageSerializerInterface
{
    /**
     * @param mixed[] $data
     * @param PropertyParameter[] $propertyParameters
     *
     * @return mixed[]
     */
    public function serialize(array $data, array $propertyParameters): array;
}
