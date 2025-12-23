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
use Sulu\Bundle\HttpCacheBundle\ReferenceStore\ReferenceStoreInterface;
use Sulu\Bundle\MediaBundle\Api\Media;
use Sulu\Bundle\MediaBundle\Entity\MediaInterface;
use Sulu\Bundle\MediaBundle\Media\FormatCache\FormatCacheInterface;
use Sulu\Bundle\MediaBundle\Media\ImageConverter\ImageConverterInterface;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Sulu\Component\Serializer\ArraySerializerInterface;

class MediaSerializer implements MediaSerializerInterface
{
    public function __construct(
        private MediaManagerInterface $mediaManager,
        private ArraySerializerInterface $arraySerializer,
        private ImageConverterInterface $imageConverter,
        private FormatCacheInterface $formatCache,
        private ReferenceStoreInterface $referenceStore,
    ) {
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
        /** @var string|null $mimeType */
        $mimeType = $formatMediaApi->getMimeType();
        $preferredExtension = null !== $mimeType
            ? ($this->imageConverter->getSupportedOutputImageFormats($mimeType)[0] ?? null)
            : null;
        if ($preferredExtension) {
            $fileName = \pathinfo($fileName)['filename'] . '.' . $preferredExtension;

            // extension brackets cannot be added here because of the urlencoding
            $fileName = \pathinfo($fileName)['filename'] . '.extension';
            $formatUri = $this->formatCache->getMediaUrl(
                $formatMediaApi->getId(),
                $fileName,
                '{format}',
                $formatMediaApi->getVersion(),
                $formatMediaApi->getSubVersion()
            );
            $formatUri = \str_replace('.extension', '.{extension}', $formatUri);

            $mediaData['formatPreferredExtension'] = $preferredExtension;
            $mediaData['formatUri'] = $formatUri;
        }

        $this->referenceStore->add((string) $apiMedia->getId(), 'media');

        return $mediaData;
    }
}
