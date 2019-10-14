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

class ContentTypeResolverCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $definition = $container->findDefinition('sulu_headless.content_resolver');

        // find all service IDs with the app.mail_transport tag
        $taggedServices = $container->findTaggedServiceIds('sulu_headless.content_type_resolver');

        $references = [];
        foreach ($taggedServices as $id => $tags) {
            $serviceDefinition = $container->getDefinition($id);

            /** @var callable $callable */
            $callable = [$serviceDefinition->getClass(), 'getContentType'];
            $references[\call_user_func($callable)] = new Reference($id);
        }

        $definition->setArgument(0, $references);
    }
}
