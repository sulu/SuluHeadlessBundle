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

namespace Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver;

use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\MediaSerializerInterface;
use Sulu\Bundle\MediaBundle\Api\Media;
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
     * @var MediaSerializerInterface
     */
    private $mediaSerializer;

    public function __construct(
        MediaManagerInterface $mediaManager,
        MediaSerializerInterface $mediaSerializer
    ) {
        $this->mediaManager = $mediaManager;
        $this->mediaSerializer = $mediaSerializer;
    }

    public function resolve($data, PropertyInterface $property, string $locale, array $attributes = []): ContentView
    {
        if (null === $data) {
            return new ContentView([]);
        }

        if (0 < \count($data) && \array_key_exists(0, $data) && $data[0] instanceof Media) {
            // we do not need to load the media entities if they are already loaded
            // this happens for example when resolving a smart-content property that uses the page provider

            return new ContentView($this->resolveApiMedias($data));
        }

        $medias = $this->mediaManager->getByIds($data['ids'] ?? [], $locale);

        return new ContentView($this->resolveApiMedias($medias), $data);
    }

    /**
     * @param Media[] $medias
     *
     * @return array[]
     */
    private function resolveApiMedias(array $medias): array
    {
        $content = [];
        foreach ($medias as $media) {
            $content[] = $this->mediaSerializer->serialize($media);
        }

        return $content;
    }
}
