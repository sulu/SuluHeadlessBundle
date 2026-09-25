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

use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\AccountSelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\BlockResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\CategorySelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\CollectionSelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\ContactAccountSelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\ContactSelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\ImageMapResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\LinkResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\MediaSelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\PageSelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\PageTreeRouteResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\SingleAccountSelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\SingleCategorySelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\SingleCollectionSelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\SingleContactSelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\SingleMediaSelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\SinglePageSelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\SingleSnippetSelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\SmartContentResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\SnippetSelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\TeaserSelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\TextEditorResolver;
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;
use Sulu\Snippet\Domain\Repository\SnippetRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->set('sulu_headless.content_resolver.single_page_selection', SinglePageSelectionResolver::class)
        ->lazy()
        ->args([
            new Reference('sulu_headless.content_resolver.page_selection'),
        ])
        ->tag('sulu_headless.content_type_resolver');

    $services->set('sulu_headless.content_resolver.page_selection', PageSelectionResolver::class)
        ->lazy()
        ->args([
            new Reference('sulu_headless.structure_resolver'),
            new Reference(PageRepositoryInterface::class),
            new Reference(ContentAggregatorInterface::class),
        ])
        ->tag('sulu_headless.content_type_resolver');

    $services->set('sulu_headless.content_resolver.snippet_selection', SnippetSelectionResolver::class)
        ->lazy()
        ->args([
            new Reference(SnippetRepositoryInterface::class),
            new Reference('sulu_headless.structure_resolver'),
            new Reference(ContentAggregatorInterface::class),
            new Reference('sulu_snippet.snippet_area_repository'),
        ])
        ->tag('sulu_headless.content_type_resolver');

    $services->set('sulu_headless.content_resolver.single_contact_selection', SingleContactSelectionResolver::class)
        ->lazy()
        ->args([
            new Reference('sulu_contact.contact_manager'),
            new Reference('sulu_headless.serializer.contact'),
        ])
        ->tag('sulu_headless.content_type_resolver');

    $services->set('sulu_headless.content_resolver.contact_selection', ContactSelectionResolver::class)
        ->lazy()
        ->args([
            new Reference('sulu_contact.contact_manager'),
            new Reference('sulu_headless.serializer.contact'),
        ])
        ->tag('sulu_headless.content_type_resolver');

    $services->set('sulu_headless.content_resolver.single_account_selection', SingleAccountSelectionResolver::class)
        ->lazy()
        ->args([
            new Reference('sulu_contact.account_manager'),
            new Reference('sulu_headless.serializer.account'),
        ])
        ->tag('sulu_headless.content_type_resolver');

    $services->set('sulu_headless.content_resolver.contact_account_selection', ContactAccountSelectionResolver::class)
        ->args([
            new Reference('sulu_contact.contact_manager'),
            new Reference('sulu_contact.account_manager'),
            new Reference('sulu_headless.serializer.contact'),
            new Reference('sulu_headless.serializer.account'),
        ])
        ->tag('sulu_headless.content_type_resolver');

    $services->set('sulu_headless.content_resolver.category_selection', CategorySelectionResolver::class)
        ->args([
            new Reference('sulu_category.category_manager'),
            new Reference('sulu_headless.serializer.category'),
        ])
        ->tag('sulu_headless.content_type_resolver');

    $services->set('sulu_headless.content_resolver.single_category_selection', SingleCategorySelectionResolver::class)
        ->args([
            new Reference('sulu_category.category_manager'),
            new Reference('sulu_headless.serializer.category'),
        ])
        ->tag('sulu_headless.content_type_resolver');

    $services->set('sulu_headless.content_resolver.single_media_selection', SingleMediaSelectionResolver::class)
        ->args([
            new Reference('sulu_headless.content_resolver.media_selection'),
        ])
        ->tag('sulu_headless.content_type_resolver');

    $services->set('sulu_headless.content_resolver.media_selection', MediaSelectionResolver::class)
        ->args([
            new Reference('sulu_media.media_manager'),
            new Reference('sulu_headless.serializer.media'),
        ])
        ->tag('sulu_headless.content_type_resolver');

    $services->set('sulu_headless.content_resolver.block', BlockResolver::class)
        ->lazy()
        ->args([
            new Reference('sulu_headless.content_resolver'),
            new Reference('sulu_admin.metadata_provider_registry'),
            tagged_iterator('sulu_content.block_visitor'),
        ])
        ->tag('sulu_headless.content_type_resolver');

    $services->set('sulu_headless.content_resolver.smart_content', SmartContentResolver::class)
        ->lazy()
        ->args([
            tagged_iterator('sulu_headless.data_provider_resolver', indexAttribute: 'provider', defaultIndexMethod: 'getDataProvider'),
            new Reference('sulu_tag.tag_manager'),
            new Reference('request_stack'),
            new Reference('sulu_tag.tag_request_handler'),
            new Reference('sulu_category.category_request_handler'),
            new Reference('sulu_audience_targeting.target_group_store', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ])
        ->tag('sulu_headless.content_type_resolver');

    $services->set('sulu_headless.content_resolver.account_selection', AccountSelectionResolver::class)
        ->lazy()
        ->args([
            new Reference('sulu_contact.account_manager'),
            new Reference('sulu_headless.serializer.account'),
        ])
        ->tag('sulu_headless.content_type_resolver');

    $services->set('sulu_headless.content_resolver.single_snippet_selection', SingleSnippetSelectionResolver::class)
        ->lazy()
        ->args([
            new Reference('sulu_headless.content_resolver.snippet_selection'),
        ])
        ->tag('sulu_headless.content_type_resolver');

    $services->set('sulu_headless.content_resolver.teaser_selection', TeaserSelectionResolver::class)
        ->lazy()
        ->args([
            new Reference('sulu_admin.teaser_manager'),
            new Reference('sulu_headless.serializer.teaser'),
        ])
        ->tag('sulu_headless.content_type_resolver');

    $services->set('sulu_headless.content_resolver.link', LinkResolver::class)
        ->lazy()
        ->args([
            new Reference('sulu_markup.link_tag.provider_pool'),
        ])
        ->tag('sulu_headless.content_type_resolver');

    $services->set('sulu_headless.content_resolver.text_editor', TextEditorResolver::class)
        ->args([
            new Reference('sulu_markup.parser'),
        ])
        ->tag('sulu_headless.content_type_resolver');

    $services->set('sulu_headless.content_resolver.image_map', ImageMapResolver::class)
        ->lazy()
        ->args([
            new Reference('sulu_media.media_manager'),
            new Reference('sulu_headless.serializer.media'),
            new Reference('sulu_headless.content_resolver'),
            new Reference('sulu_admin.metadata_provider_registry'),
        ])
        ->tag('sulu_headless.content_type_resolver');

    $services->set('sulu_headless.content_resolver.page_tree_route', PageTreeRouteResolver::class)
        ->lazy()
        ->tag('sulu_headless.content_type_resolver');

    $services->set('sulu_headless.content_resolver.collection_selection', CollectionSelectionResolver::class)
        ->lazy()
        ->args([
            new Reference('sulu_media.collection_repository'),
            new Reference('sulu_headless.serializer.collection'),
        ])
        ->tag('sulu_headless.content_type_resolver');

    $services->set('sulu_headless.content_resolver.single_collection_selection', SingleCollectionSelectionResolver::class)
        ->lazy()
        ->args([
            new Reference('sulu_headless.content_resolver.collection_selection'),
        ])
        ->tag('sulu_headless.content_type_resolver');
};
