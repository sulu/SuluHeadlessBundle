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

use Sulu\Article\Domain\Repository\ArticleRepositoryInterface;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\ArticleSelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\SingleArticleSelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\DataProviderResolver\ArticleDataProviderResolver;
use Sulu\Bundle\HeadlessBundle\Content\DataProviderResolver\ArticlePageTreeDataProviderResolver;
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Symfony\Component\DependencyInjection\Reference;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    // Content Type Resolvers
    $services->set('sulu_headless.content_resolver.article_selection', ArticleSelectionResolver::class)
        ->lazy()
        ->args([
            new Reference('sulu_headless.structure_resolver'),
            new Reference(ArticleRepositoryInterface::class),
            new Reference(ContentAggregatorInterface::class),
        ])
        ->tag('sulu_headless.content_type_resolver');

    $services->set('sulu_headless.content_resolver.single_article_selection', SingleArticleSelectionResolver::class)
        ->lazy()
        ->args([
            new Reference('sulu_headless.content_resolver.article_selection'),
        ])
        ->tag('sulu_headless.content_type_resolver');

    // Data Provider Resolvers
    $services->set('sulu_headless.provider_resolver.article', ArticleDataProviderResolver::class)
        ->args([
            new Reference('sulu_article.article_smart_content_provider'),
            new Reference('sulu_headless.structure_resolver'),
            new Reference(ArticleRepositoryInterface::class),
            new Reference(ContentAggregatorInterface::class),
        ])
        ->tag('sulu_headless.data_provider_resolver');

    $services->set('sulu_headless.provider_resolver.article_page_tree', ArticlePageTreeDataProviderResolver::class)
        ->args([
            new Reference('sulu_article.page_tree_article_smart_content_provider'),
            new Reference('sulu_headless.structure_resolver'),
            new Reference(ArticleRepositoryInterface::class),
            new Reference(ContentAggregatorInterface::class),
        ])
        ->tag('sulu_headless.data_provider_resolver');
};
