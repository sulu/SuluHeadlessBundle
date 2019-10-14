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

namespace Sulu\Bundle\HeadlessBundle\Content\Resolver;

use JMS\Serializer\SerializerInterface;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\MediaBundle\Media\FormatCache\FormatCacheInterface;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Sulu\Component\Content\Compat\PropertyInterface;

class MediaSelectionResolver implements ContentTypeResolverInterface
{
    public static function getContentType(): string
    {
        return 'media_selection';
    }

    /**
     * @var MediaManagerInterface
     */
    private $mediaManager;

    /**
     * @var FormatCacheInterface
     */
    private $formatCache;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    public function __construct(
        MediaManagerInterface $mediaManager,
        FormatCacheInterface $formatCache,
        SerializerInterface $serializer
    ) {
        $this->mediaManager = $mediaManager;
        $this->serializer = $serializer;
        $this->formatCache = $formatCache;
    }

    public function resolve($data, PropertyInterface $property, string $locale, array $attributes = []): ContentView
    {
        $medias = $this->mediaManager->getByIds($data['ids'], $locale);

        $content = [];
        foreach ($medias as $media) {
            /** @var array $mediaData */
            $mediaData = json_decode($this->serializer->serialize($media, 'json'), true);
            // FIXME change to array-serializer when updating sulu

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

            $content[] = $mediaData;
        }

        return new ContentView($content, $data);
    }
}
