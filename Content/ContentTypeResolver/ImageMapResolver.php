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
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TypedFormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderRegistry;
use Sulu\Bundle\HeadlessBundle\Content\ContentResolverInterface;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\MediaSerializerInterface;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;

class ImageMapResolver implements ContentTypeResolverInterface
{
    public static function getContentType(): string
    {
        return 'image_map';
    }

    public function __construct(
        private MediaManagerInterface $mediaManager,
        private MediaSerializerInterface $mediaSerializer,
        private ContentResolverInterface $contentResolver,
        private MetadataProviderRegistry $metadataProviderRegistry,
    ) {
    }

    public function resolve(mixed $data, FieldMetadata $fieldMetadata, string $locale, array $attributes = []): ContentView
    {
        if (!\is_array($data)) {
            return new ContentView([], []);
        }

        $imageId = $data['imageId'] ?? null;
        $hotspots = $data['hotspots'] ?? [];

        $content = [];
        $view = [];

        if ($imageId) {
            $media = $this->mediaManager->getById($imageId, $locale);
            $content['image'] = $this->mediaSerializer->serialize($media->getEntity(), $locale);
            $view['image'] = ['id' => $imageId];
        }

        $hotspotTypes = $fieldMetadata->getTypes();
        $globalBlocksMetadata = $this->getGlobalBlocksMetadata($locale);

        foreach ($hotspots as $hotspot) {
            if (!\is_array($hotspot) || !isset($hotspot['type'])) {
                continue;
            }

            $hotspotTypeName = $hotspot['type'];
            $hotspotTypeMetadata = $hotspotTypes[$hotspotTypeName] ?? null;

            if (!$hotspotTypeMetadata) {
                $content['hotspots'][] = $hotspot;
                $view['hotspots'][] = [];
                continue;
            }

            $globalBlockType = $this->getGlobalBlockType($hotspotTypeMetadata);
            if ($globalBlockType && isset($globalBlocksMetadata[$globalBlockType])) {
                $hotspotTypeMetadata = $globalBlocksMetadata[$globalBlockType];
            }

            $hotspotView = [];
            $hotspotFieldMetadata = $hotspotTypeMetadata->getFlatFieldMetadata();

            foreach ($hotspotFieldMetadata as $fieldName => $childFieldMetadata) {
                $fieldValue = $hotspot[$fieldName] ?? null;
                $result = $this->contentResolver->resolve($fieldValue, $childFieldMetadata, $locale, $attributes);
                $hotspot[$fieldName] = $result->getContent();
                $hotspotView[$fieldName] = $result->getView();
            }

            $content['hotspots'][] = $hotspot;
            $view['hotspots'][] = $hotspotView;
        }

        return new ContentView($content, $view);
    }

    /**
     * @return array<string, FormMetadata>
     */
    private function getGlobalBlocksMetadata(string $locale): array
    {
        $typedFormMetadata = $this->metadataProviderRegistry->getMetadataProvider('form')
            ->getMetadata('block', $locale, []);

        if (!$typedFormMetadata instanceof TypedFormMetadata) {
            return [];
        }

        return $typedFormMetadata->getForms();
    }

    private function getGlobalBlockType(FormMetadata $formMetadata): ?string
    {
        $tag = $formMetadata->getTagsByName('sulu.global_block')[0] ?? null;

        /** @var string|null $result */
        $result = $tag?->getAttribute('global_block');

        return $result;
    }
}
