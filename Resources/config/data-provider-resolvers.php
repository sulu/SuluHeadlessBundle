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

use Sulu\Bundle\HeadlessBundle\Content\DataProviderResolver\AccountDataProviderResolver;
use Sulu\Bundle\HeadlessBundle\Content\DataProviderResolver\ContactDataProviderResolver;
use Sulu\Bundle\HeadlessBundle\Content\DataProviderResolver\MediaDataProviderResolver;
use Sulu\Bundle\HeadlessBundle\Content\DataProviderResolver\PageDataProviderResolver;
use Sulu\Bundle\HeadlessBundle\Content\DataProviderResolver\SnippetDataProviderResolver;
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;
use Sulu\Snippet\Domain\Repository\SnippetRepositoryInterface;
use Symfony\Component\DependencyInjection\Reference;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->set('sulu_headless.provider_resolver.media', MediaDataProviderResolver::class)
        ->args([
            new Reference('sulu_media.media_smart_content_provider'),
            new Reference('sulu_headless.serializer.media'),
            new Reference('sulu.repository.media'),
        ])
        ->tag('sulu_headless.data_provider_resolver');

    $services->set('sulu_headless.provider_resolver.contact', ContactDataProviderResolver::class)
        ->args([
            new Reference('sulu_contact.contact_smart_content_provider'),
            new Reference('sulu_headless.serializer.contact'),
            new Reference('sulu.repository.contact'),
        ])
        ->tag('sulu_headless.data_provider_resolver');

    $services->set('sulu_headless.provider_resolver.account', AccountDataProviderResolver::class)
        ->args([
            new Reference('sulu_contact.account_smart_content_provider'),
            new Reference('sulu_headless.serializer.account'),
            new Reference('sulu.repository.account'),
        ])
        ->tag('sulu_headless.data_provider_resolver');

    $services->set('sulu_headless.provider_resolver.page', PageDataProviderResolver::class)
        ->args([
            new Reference('sulu_page.page_smart_content_provider'),
            new Reference('sulu_headless.structure_resolver'),
            new Reference(PageRepositoryInterface::class),
            new Reference(ContentAggregatorInterface::class),
        ])
        ->tag('sulu_headless.data_provider_resolver');

    $services->set('sulu_headless.provider_resolver.snippet', SnippetDataProviderResolver::class)
        ->args([
            new Reference('sulu_snippet.snippet_smart_content_provider'),
            new Reference('sulu_headless.structure_resolver'),
            new Reference(SnippetRepositoryInterface::class),
            new Reference(ContentAggregatorInterface::class),
        ])
        ->tag('sulu_headless.data_provider_resolver');
};
