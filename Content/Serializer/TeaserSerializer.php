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
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Sulu\Bundle\PageBundle\Teaser\Teaser;
use Sulu\Component\Serializer\ArraySerializerInterface;

class TeaserSerializer implements TeaserSerializerInterface
{
    /**
     * @var ArraySerializerInterface
     */
    private $arraySerializer;

    /**
     * @var MediaSerializerInterface
     */
    private $mediaSerializer;

    /**
     * @var MediaManagerInterface
     */
    private $mediaManager;

    public function __construct(
        ArraySerializerInterface $arraySerializer,
        MediaSerializerInterface $mediaSerializer,
        MediaManagerInterface $mediaManager
    ) {
        $this->arraySerializer = $arraySerializer;
        $this->mediaSerializer = $mediaSerializer;
        $this->mediaManager = $mediaManager;
    }

    /**
     * @return mixed[]
     */
    public function serialize(Teaser $teaser, string $locale, ?SerializationContext $context = null): array
    {
        $teaserData = $this->arraySerializer->serialize($teaser, $context);
        unset($teaserData['mediaId']);

        $mediaId = $teaser->getMediaId();
        if ($mediaId) {
            $media = $this->mediaManager->getEntityById($mediaId);
            $teaserData['media'] = $this->mediaSerializer->serialize($media, $locale, $context);
        }

        return $teaserData;
    }
}
