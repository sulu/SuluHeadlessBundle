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

use Sulu\Bundle\HeadlessBundle\Content\ContentResolverInterface;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\MediaSerializerInterface;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Sulu\Component\Content\Compat\PropertyInterface;

class ImageMapResolver implements ContentTypeResolverInterface
{
    /**
     * @var MediaManagerInterface
     */
    private $mediaManager;

    /**
     * @var MediaSerializerInterface
     */
    private $mediaSerializer;

    /**
     * @var ContentResolverInterface
     */
    private $contentResolver;

    public static function getContentType(): string
    {
        return 'image_map';
    }

    public function __construct(
        MediaManagerInterface $mediaManager,
        MediaSerializerInterface $mediaSerializer,
        ContentResolverInterface $contentResolver
    ) {
        $this->mediaManager = $mediaManager;
        $this->mediaSerializer = $mediaSerializer;
        $this->contentResolver = $contentResolver;
    }

    public function resolve($data, PropertyInterface $property, string $locale, array $attributes = []): ContentView
    {
        $imageId = $data['imageId'] ?? null;
        $hotspots = $data['hotspots'] ?? [];

        $content = [];
        $view = [];
        if ($imageId) {
            $media = $this->mediaManager->getById($imageId, $locale);
            $content['image'] = $this->mediaSerializer->serialize($media->getEntity(), $locale);
            $view['image'] = ['id' => $imageId];
        }

        foreach ($hotspots as $i => $hotspot) {
            $hotspotView = [];

            $propertyType = $property->initProperties($i, $hotspot['type']);
            foreach ($propertyType->getChildProperties() as $childProperty) {
                $key = $childProperty->getName();

                $childProperty->setValue($hotspot[$key] ?? null);
                $result = $this->contentResolver->resolve($childProperty->getValue(), $childProperty, $locale, $attributes);
                $hotspot[$key] = $result->getContent();
                $hotspotView[$key] = $result->getView();
            }

            $content['hotspots'][] = $hotspot;
            $view['hotspots'][] = $hotspotView;
        }

        return new ContentView($content, $view);
    }
}
