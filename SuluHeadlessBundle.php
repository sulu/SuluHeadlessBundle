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

namespace Sulu\Bundle\HeadlessBundle;

use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\ContentTypeResolverInterface;
use Sulu\Bundle\HeadlessBundle\Content\DataProviderResolver\DataProviderResolverInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Register the bundles compiler passes.
 */
class SuluHeadlessBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->registerForAutoconfiguration(ContentTypeResolverInterface::class)
            ->addTag('sulu_headless.content_type_resolver');

        $container->registerForAutoconfiguration(DataProviderResolverInterface::class)
            ->addTag('sulu_headless.data_provider_resolver');
    }
}
