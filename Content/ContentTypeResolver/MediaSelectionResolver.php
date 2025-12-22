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

use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\MediaSerializerInterface;
use Sulu\Bundle\MediaBundle\Api\Media;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;

class MediaSelectionResolver implements ContentTypeResolverInterface
{
    public static function getContentType(): string
    {
        return 'media_selection';
    }

    public function __construct(
        private MediaManagerInterface $mediaManager,
        private MediaSerializerInterface $mediaSerializer,
    ) {
    }

    public function resolve(mixed $data, FieldMetadata $fieldMetadata, string $locale, array $attributes = []): ContentView
    {
        if (empty($data) || !\is_array($data)) {
            return new ContentView([], ['ids' => []]);
        }

        $ids = $data['ids'] ?? [];

        $content = [];
        if (\is_array($ids) && \count($ids)) {
            $medias = $this->mediaManager->getByIds($ids, $locale);
            $content = $this->resolveApiMedias($medias, $locale);
        }

        return new ContentView($content, \array_merge(['ids' => []], $data));
    }

    /**
     * @param Media[] $medias
     *
     * @return array<array<string, mixed>>
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
