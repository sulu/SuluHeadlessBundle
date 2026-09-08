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

use Sulu\Bundle\HeadlessBundle\Content\Serializer\AccountSerializer;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\AccountSerializerInterface;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\CategorySerializer;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\CategorySerializerInterface;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\CollectionSerializer;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\CollectionSerializerInterface;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\ContactSerializer;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\ContactSerializerInterface;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\MediaSerializer;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\MediaSerializerInterface;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\TeaserSerializer;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\TeaserSerializerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->set('sulu_headless.serializer.account', AccountSerializer::class)
        ->args([
            new Reference('sulu_contact.account_manager'),
            new Reference('sulu_core.array_serializer'),
            new Reference('sulu_headless.serializer.media'),
            new Reference('sulu_media.media_manager'),
            new Reference('sulu_http_cache.reference_store', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ]);
    $services->alias(AccountSerializerInterface::class, 'sulu_headless.serializer.account');

    $services->set('sulu_headless.serializer.contact', ContactSerializer::class)
        ->args([
            new Reference('sulu_contact.contact_manager'),
            new Reference('sulu_core.array_serializer'),
            new Reference('sulu_media.media_manager'),
            new Reference('sulu_headless.serializer.media'),
            new Reference('sulu_contact.contact_title_repository'),
            new Reference('sulu_contact.position_repository'),
            new Reference('sulu_http_cache.reference_store', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ]);
    $services->alias(ContactSerializerInterface::class, 'sulu_headless.serializer.contact');

    $services->set('sulu_headless.serializer.media', MediaSerializer::class)
        ->args([
            new Reference('sulu_media.media_manager'),
            new Reference('sulu_core.array_serializer'),
            new Reference('sulu_media.image.converter'),
            new Reference('sulu_media.format_cache'),
            new Reference('sulu_http_cache.reference_store', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ]);
    $services->alias(MediaSerializerInterface::class, 'sulu_headless.serializer.media');

    $services->set('sulu_headless.serializer.category', CategorySerializer::class)
        ->args([
            new Reference('sulu_category.category_manager'),
            new Reference('sulu_core.array_serializer'),
            new Reference('sulu_headless.serializer.media'),
        ]);
    $services->alias(CategorySerializerInterface::class, 'sulu_headless.serializer.category');

    $services->set('sulu_headless.serializer.teaser', TeaserSerializer::class)
        ->args([
            new Reference('sulu_core.array_serializer'),
            new Reference('sulu_headless.serializer.media'),
            new Reference('sulu_media.media_manager'),
            new Reference('sulu_http_cache.reference_store', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ]);
    $services->alias(TeaserSerializerInterface::class, 'sulu_headless.serializer.teaser');

    $services->set('sulu_headless.serializer.collection', CollectionSerializer::class);
    $services->alias(CollectionSerializerInterface::class, 'sulu_headless.serializer.collection');
};
