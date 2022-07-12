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
        if (empty($data)) {
            return new ContentView([], ['ids' => []]);
        }

        $ids = $data['ids'] ?? [];

        $content = [];
        if (\count($ids)) {
            $medias = $this->mediaManager->getByIds($ids, $locale);
            $content = $this->resolveApiMedias($medias, $locale);
        }

        return new ContentView($content, \array_merge(['ids' => []], $data));
    }

    /**
     * @param Media[] $medias
     *
     * @return array[]
     */
    private function resolveApiMedias(array $medias, string $locale): array
    {
        $content = [];
        foreach ($medias as $media) {
            $content[] = $this->mediaSerializer->serialize($media->getEntity(), $locale);
        }

        return $content;
    }
}
