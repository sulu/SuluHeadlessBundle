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

use JMS\Serializer\SerializationContext;
use Sulu\Bundle\PageBundle\Teaser\Teaser;

interface TeaserSerializerInterface
{
    /**
     * @return mixed[]
     */
    public function serialize(Teaser $teaser, string $locale, ?SerializationContext $context = null): array;
}
