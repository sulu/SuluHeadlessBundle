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
use Sulu\Bundle\CategoryBundle\Category\CategoryManagerInterface;
use Sulu\Bundle\CategoryBundle\Entity\CategoryInterface;
use Sulu\Component\Serializer\ArraySerializerInterface;

class CategorySerializer implements CategorySerializerInterface
{
    /**
     * @var CategoryManagerInterface
     */
    private $categoryManager;

    /**
     * @var ArraySerializerInterface
     */
    private $arraySerializer;

    /**
     * @var MediaSerializerInterface
     */
    private $mediaSerializer;

    public function __construct(
        CategoryManagerInterface $categoryManager,
        ArraySerializerInterface $arraySerializer,
        MediaSerializerInterface $mediaSerializer
    ) {
        $this->categoryManager = $categoryManager;
        $this->arraySerializer = $arraySerializer;
        $this->mediaSerializer = $mediaSerializer;
    }

    /**
     * @return mixed[]
     */
    public function serialize(CategoryInterface $category, string $locale, ?SerializationContext $context = null): array
    {
        $apiCategory = $this->categoryManager->getApiObject($category, $locale);
        $categoryData = $this->arraySerializer->serialize($apiCategory, $context);

        unset($categoryData['defaultLocale']);
        unset($categoryData['meta']);

        $categoryData['medias'] = [];
        foreach ($apiCategory->getMedias() as $media) {
            $categoryData['medias'][] = $this->mediaSerializer->serialize($media->getEntity(), $locale);
        }

        return $categoryData;
    }
}
