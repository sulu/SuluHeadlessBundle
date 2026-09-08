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

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sulu\Bundle\HeadlessBundle\EventSubscriber\NavigationInvalidationSubscriber;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->set('sulu_headless.event_subscriber.navigation_invalidation', NavigationInvalidationSubscriber::class)
        ->args([
            new Reference('sulu_http_cache.cache_manager', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            new Reference('sulu_content.content_aggregator'),
        ])
        ->tag('kernel.event_subscriber');
};
