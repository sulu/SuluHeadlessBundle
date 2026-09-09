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

use Psr\Container\ContainerInterface as PsrContainerInterface;
use Sulu\Bundle\HeadlessBundle\Controller\AnalyticsController;
use Sulu\Bundle\HeadlessBundle\Controller\HeadlessWebsiteController;
use Sulu\Bundle\HeadlessBundle\Controller\NavigationController;
use Sulu\Bundle\HeadlessBundle\Controller\SearchController;
use Sulu\Bundle\HeadlessBundle\Controller\SnippetAreaController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->set('sulu_headless.controller.navigation', NavigationController::class)
        ->public()
        ->args([
            new Reference('sulu_page.navigation_repository'),
            new Reference('jms_serializer.serializer'),
            new Reference('sulu_http_cache.reference_store'),
            new Reference('sulu_core.webspace.request_analyzer'),
            new Reference('sulu_headless.serializer.media'),
            new Reference('sulu_headless.serializer.category'),
            '%sulu_http_cache.cache.max_age%',
            '%sulu_http_cache.cache.shared_max_age%',
            '%sulu_headless.navigation.cache_lifetime%',
        ]);
    $services->alias(NavigationController::class, 'sulu_headless.controller.navigation')
        ->public();

    $services->set('sulu_headless.controller.analytics', AnalyticsController::class)
        ->public()
        ->args([
            new Reference('jms_serializer.serializer'),
            new Reference('sulu.repository.analytics'),
            '%kernel.environment%',
            '%sulu_http_cache.cache.max_age%',
            '%sulu_http_cache.cache.shared_max_age%',
            '%sulu_headless.navigation.cache_lifetime%',
        ]);
    $services->alias(AnalyticsController::class, 'sulu_headless.controller.analytics')
        ->public();

    $services->set('sulu_headless.controller.snippet_area', SnippetAreaController::class)
        ->public()
        ->args([
            new Reference('sulu_snippet.snippet_area_repository'),
            new Reference('sulu_snippet.snippet_repository'),
            new Reference('sulu_content.content_aggregator'),
            new Reference('sulu_headless.structure_resolver'),
            new Reference('sulu_http_cache.reference_store', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            '%sulu_http_cache.cache.max_age%',
            '%sulu_http_cache.cache.shared_max_age%',
            '%sulu_headless.snippet_area.cache_lifetime%',
            new Reference('sulu_http_cache.cache_lifetime.request_store', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ]);
    $services->alias(SnippetAreaController::class, 'sulu_headless.controller.snippet_area')
        ->public();

    $services->set('sulu_headless.controller.search', SearchController::class)
        ->public()
        ->args([
            new Reference('CmsIg\Seal\EngineInterface'),
            new Reference('sulu.repository.media'),
            new Reference('sulu_headless.serializer.media'),
        ]);
    $services->alias(SearchController::class, 'sulu_headless.controller.search')
        ->public();

    $services->set('sulu_headless.controller.website', HeadlessWebsiteController::class)
        ->public()
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments')
        ->call('setContainer', [
            new Reference(PsrContainerInterface::class),
        ]);
    $services->alias(HeadlessWebsiteController::class, 'sulu_headless.controller.website')
        ->public();
};
