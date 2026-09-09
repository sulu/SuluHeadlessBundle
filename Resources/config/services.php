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

use Sulu\Bundle\HeadlessBundle\Content\ContentResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentResolverInterface;
use Sulu\Bundle\HeadlessBundle\Content\ExtensionResolver\ExcerptResolver;
use Sulu\Bundle\HeadlessBundle\Content\ExtensionResolver\ExtensionResolverProvider;
use Sulu\Bundle\HeadlessBundle\Content\ExtensionResolver\SeoResolver;
use Sulu\Bundle\HeadlessBundle\Content\StructureResolver;
use Sulu\Bundle\HeadlessBundle\Content\StructureResolverInterface;
use Symfony\Component\DependencyInjection\Reference;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    // Extension Resolvers
    $services->set('sulu_headless.excerpt_resolver', ExcerptResolver::class)
        ->args([
            new Reference('sulu_admin.form_metadata_provider'),
            new Reference('sulu_headless.content_resolver'),
        ])
        ->tag('sulu_headless.extension_resolver');

    $services->set('sulu_headless.seo_resolver', SeoResolver::class)
        ->args([
            new Reference('sulu_admin.form_metadata_provider'),
            new Reference('sulu_headless.content_resolver'),
        ])
        ->tag('sulu_headless.extension_resolver');

    $services->set('sulu_headless.extension_resolver_provider', ExtensionResolverProvider::class)
        ->args([
            tagged_iterator('sulu_headless.extension_resolver'),
        ]);

    $services->set('sulu_headless.structure_resolver', StructureResolver::class)
        ->public()
        ->args([
            new Reference('sulu_admin.form_metadata_provider'),
            new Reference('sulu_headless.content_resolver'),
            new Reference('sulu_http_cache.reference_store'),
            new Reference('sulu_headless.extension_resolver_provider'),
        ]);
    $services->alias(StructureResolverInterface::class, 'sulu_headless.structure_resolver');

    $services->set('sulu_headless.content_resolver', ContentResolver::class)
        ->public()
        ->args([
            tagged_iterator('sulu_headless.content_type_resolver', indexAttribute: 'type', defaultIndexMethod: 'getContentType'),
        ]);
    $services->alias(ContentResolverInterface::class, 'sulu_headless.content_resolver');
};
