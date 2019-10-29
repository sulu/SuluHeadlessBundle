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

namespace Sulu\Bundle\HeadlessBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class DataProviderResolverCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $definition = $container->findDefinition('sulu_headless.content_resolver.smart_content');

        // find all service IDs with the sulu_headless.data_provider_resolver tag
        $taggedServices = $container->findTaggedServiceIds('sulu_headless.data_provider_resolver');

        $references = [];
        foreach ($taggedServices as $id => $tags) {
            $serviceDefinition = $container->getDefinition($id);

            /** @var callable $callable */
            $callable = [$serviceDefinition->getClass(), 'getDataProvider'];
            $references[\call_user_func($callable)] = new Reference($id);
        }

        $definition->setArgument(0, $references);
    }
}
