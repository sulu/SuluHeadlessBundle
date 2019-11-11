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
use Sulu\Bundle\CategoryBundle\Api\Category;
use Sulu\Component\Serializer\ArraySerializerInterface;

class CategorySerializer
{
    /**
     * @var ArraySerializerInterface
     */
    private $arraySerializer;

    /**
     * @var MediaSerializer
     */
    private $mediaSerializer;

    public function __construct(
        ArraySerializerInterface $arraySerializer,
        MediaSerializer $mediaSerializer
    ) {
        $this->arraySerializer = $arraySerializer;
        $this->mediaSerializer = $mediaSerializer;
    }

    /**
     * @return mixed[]
     */
    public function serialize(Category $category, ?SerializationContext $context = null): array
    {
        $medias = $category->getMedias();
        $categoryData = $this->arraySerializer->serialize($category, $context);

        unset($categoryData['defaultLocale']);
        unset($categoryData['meta']);

        $categoryData['medias'] = [];
        foreach ($medias as $media) {
            $categoryData['medias'][] = $this->mediaSerializer->serialize($media);
        }

        return $categoryData;
    }
}
