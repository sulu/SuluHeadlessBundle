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
use Sulu\Bundle\MediaBundle\Entity\MediaInterface;
use Sulu\Bundle\MediaBundle\Media\FormatCache\FormatCacheInterface;
use Sulu\Bundle\MediaBundle\Media\ImageConverter\ImageConverterInterface;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Sulu\Bundle\WebsiteBundle\ReferenceStore\ReferenceStoreInterface;
use Sulu\Component\Serializer\ArraySerializerInterface;

class MediaSerializer implements MediaSerializerInterface
{
    /**
     * @var MediaManagerInterface
     */
    private $mediaManager;

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

    /**
     * @var ReferenceStoreInterface
     */
    private $referenceStore;

    public function __construct(
        MediaManagerInterface $mediaManager,
        ArraySerializerInterface $arraySerializer,
        ImageConverterInterface $imageConverter,
        FormatCacheInterface $formatCache,
        ReferenceStoreInterface $referenceStore
    ) {
        $this->mediaManager = $mediaManager;
        $this->arraySerializer = $arraySerializer;
        $this->imageConverter = $imageConverter;
        $this->formatCache = $formatCache;
        $this->referenceStore = $referenceStore;
    }

    /**
     * @return mixed[]
     */
    public function serialize(MediaInterface $media, string $locale, ?SerializationContext $context = null): array
    {
        $apiMedia = new Media($media, $locale);
        $apiMedia = $this->mediaManager->addFormatsAndUrl($apiMedia);
        $mediaData = $this->arraySerializer->serialize($apiMedia, $context);

        unset($mediaData['formats']);
        unset($mediaData['storageOptions']);
        unset($mediaData['thumbnails']);
        unset($mediaData['versions']);
        unset($mediaData['downloadCounter']);
        unset($mediaData['adminUrl']);
        unset($mediaData['_hash']);

        $formatMediaApi = $apiMedia;
        /** @var MediaInterface|null $previewImage */
        $previewImage = $media->getPreviewImage();
        if ($previewImage) {
            $formatMediaApi = new Media($previewImage, $locale);
        }

        /** @var string $fileName */
        $fileName = $formatMediaApi->getName();

        // replace extension of filename with preferred media extension if possible
        $preferredExtension = $this->imageConverter->getSupportedOutputImageFormats($formatMediaApi->getMimeType())[0] ?? null;
        if ($preferredExtension) {
            $fileName = \pathinfo($fileName)['filename'] . '.' . $preferredExtension;
        }

        $mediaData['formatUri'] = $this->formatCache->getMediaUrl(
            $formatMediaApi->getId(),
            $fileName,
            '{format}',
            $formatMediaApi->getVersion(),
            $formatMediaApi->getSubVersion()
        );

        $this->referenceStore->add($apiMedia->getId());

        return $mediaData;
    }
}
