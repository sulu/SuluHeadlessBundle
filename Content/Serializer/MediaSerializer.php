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
use Sulu\Bundle\MediaBundle\Media\ImageConverter\ImageConverterInterface;
use Sulu\Component\Serializer\ArraySerializerInterface;

class MediaSerializer implements MediaSerializerInterface
{
    /**
     * @var ArraySerializerInterface
     */
    private $arraySerializer;

    /**
     * @var ImageConverterInterface
     */
    private $imageConverter;

    /**
     * @var FormatCacheInterface
     */
    private $formatCache;

    public function __construct(
        ArraySerializerInterface $arraySerializer,
        ImageConverterInterface $imageConverter,
        FormatCacheInterface $formatCache
    ) {
        $this->arraySerializer = $arraySerializer;
        $this->imageConverter = $imageConverter;
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

        /** @var string $fileName */
        $fileName = $media->getName();

        // replace extension of filename with preferred media extension if possible
        $preferredExtension = $this->imageConverter->getSupportedOutputImageFormats($media->getMimeType())[0] ?? null;
        if ($preferredExtension) {
            $fileName = pathinfo($fileName)['filename'] . '.' . $preferredExtension;
        }

        $mediaData['formatUri'] = $this->formatCache->getMediaUrl(
            $media->getId(),
            $fileName,
            '{format}',
            $media->getVersion(),
            $media->getSubVersion()
        );

        return $mediaData;
    }
}
