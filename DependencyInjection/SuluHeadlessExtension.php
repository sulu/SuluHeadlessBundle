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

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class SuluHeadlessExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter(
            'sulu_headless.navigation.cache_lifetime',
            $config['navigation']['cache_lifetime']
        );
        $container->setParameter(
            'sulu_headless.snippet_area.cache_lifetime',
            $config['snippet_area']['cache_lifetime']
        );

        $loader = new Loader\PhpFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.php');
        $loader->load('controllers.php');
        $loader->load('content-type-resolvers.php');
        $loader->load('data-provider-resolvers.php');
        $loader->load('serializers.php');
        $loader->load('event-subscribers.php');
        $loader->load('article-resolvers.php');
    }
}
