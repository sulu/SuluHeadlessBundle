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

namespace Sulu\Bundle\HeadlessBundle\Content\Serializer;

use JMS\Serializer\SerializationContext;
use Sulu\Bundle\MediaBundle\Api\Media;
use Sulu\Bundle\MediaBundle\Media\FormatCache\FormatCacheInterface;
use Sulu\Component\Serializer\ArraySerializerInterface;

class MediaSerializer
{
    /**
     * @var ArraySerializerInterface
     */
    private $arraySerializer;

    /**
     * @var FormatCacheInterface
     */
    private $formatCache;

    public function __construct(ArraySerializerInterface $arraySerializer, FormatCacheInterface $formatCache)
    {
        $this->arraySerializer = $arraySerializer;
        $this->formatCache = $formatCache;
    }

    /**
     * @return mixed[]
     */
    public function serialize(Media $media, ?SerializationContext $context = null): array
    {
        $mediaData = $this->arraySerializer->serialize($media, $context);

        unset($mediaData['formats']);
        unset($mediaData['storageOptions']);
        unset($mediaData['thumbnails']);
        unset($mediaData['versions']);
        unset($mediaData['downloadCounter']);
        unset($mediaData['_hash']);

        /** @var string $name */
        $name = $media->getName();

        $mediaData['formatUri'] = $this->formatCache->getMediaUrl(
            $media->getId(),
            $name,
            '{format}',
            $media->getVersion(),
            $media->getSubVersion()
        );

        return $mediaData;
    }
}
