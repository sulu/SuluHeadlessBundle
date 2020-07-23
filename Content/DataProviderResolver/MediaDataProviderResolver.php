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

namespace Sulu\Bundle\HeadlessBundle\Content\DataProviderResolver;

use Sulu\Bundle\HeadlessBundle\Content\Serializer\MediaSerializerInterface;
use Sulu\Bundle\MediaBundle\Api\Media;
use Sulu\Component\Content\Compat\PropertyParameter;
use Sulu\Component\Media\SmartContent\MediaDataProvider;
use Sulu\Component\SmartContent\Configuration\ProviderConfigurationInterface;

class MediaDataProviderResolver implements DataProviderResolverInterface
{
    public static function getDataProvider(): string
    {
        return 'media';
    }

    /**
     * @var MediaDataProvider
     */
    private $mediaDataProvider;

    /**
     * @var MediaSerializerInterface
     */
    private $mediaSerializer;

    public function __construct(
        MediaDataProvider $mediaDataProvider,
        MediaSerializerInterface $mediaSerializer
    ) {
        $this->mediaDataProvider = $mediaDataProvider;
        $this->mediaSerializer = $mediaSerializer;
    }

    public function getProviderConfiguration(): ProviderConfigurationInterface
    {
        return $this->mediaDataProvider->getConfiguration();
    }

    /**
     * @return PropertyParameter[]
     */
    public function getProviderDefaultParams(): array
    {
        return $this->mediaDataProvider->getDefaultPropertyParameter();
    }

    public function resolve(
        array $filters,
        array $propertyParameters,
        array $options = [],
        ?int $limit = null,
        int $page = 1,
        ?int $pageSize = null
    ): DataProviderResult {
        $providerResult = $this->mediaDataProvider->resolveResourceItems(
            $filters,
            $propertyParameters,
            $options,
            $limit,
            $page,
            $pageSize
        );

        $items = [];
        foreach ($providerResult->getItems() as $providerItem) {
            /** @var Media $media */
            $media = $providerItem->getResource();
            $items[] = $this->mediaSerializer->serialize($media->getEntity(), $options['locale']);
        }

        return new DataProviderResult($items, $providerResult->getHasNextPage());
    }
}
