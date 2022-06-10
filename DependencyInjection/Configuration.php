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

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('sulu_headless');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode->children()
            ->arrayNode('navigation')
            ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('cache_lifetime')->defaultValue(86400)->end()
                ->end()
            ->end()
            ->arrayNode('snippet_area')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('cache_lifetime')->defaultValue(86400)->end()
                ->end()
            ->end()
        ->end();

        return $treeBuilder;
    }
}
