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

namespace Sulu\Bundle\HeadlessBundle\Content\DataProviderResolver;

use Sulu\Component\Content\Compat\PropertyParameter;
use Sulu\Component\SmartContent\Configuration\ProviderConfigurationInterface;

interface DataProviderResolverInterface
{
    public function getProviderConfiguration(): ProviderConfigurationInterface;

    /**
     * @return PropertyParameter[]
     */
    public function getProviderDefaultParams(): array;

    /**
     * @param mixed[] $filters
     * @param PropertyParameter[] $propertyParameters
     * @param mixed[] $options
     */
    public function resolve(
        array $filters,
        array $propertyParameters,
        array $options = [],
        ?int $limit = null,
        int $page = 1,
        ?int $pageSize = null
    ): DataProviderResult;
}
