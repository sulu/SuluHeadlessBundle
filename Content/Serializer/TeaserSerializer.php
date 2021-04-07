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
use Sulu\Bundle\WebsiteBundle\ReferenceStore\ReferenceStoreNotExistsException;
use Sulu\Bundle\WebsiteBundle\ReferenceStore\ReferenceStorePoolInterface;
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

    /**
     * @var ReferenceStorePoolInterface
     */
    private $referenceStorePool;

    public function __construct(
        ArraySerializerInterface $arraySerializer,
        MediaSerializerInterface $mediaSerializer,
        MediaManagerInterface $mediaManager,
        ReferenceStorePoolInterface $referenceStorePool
    ) {
        $this->arraySerializer = $arraySerializer;
        $this->mediaSerializer = $mediaSerializer;
        $this->mediaManager = $mediaManager;
        $this->referenceStorePool = $referenceStorePool;
    }

    /**
     * @return mixed[]
     */
    public function serialize(Teaser $teaser, string $locale, ?SerializationContext $context = null): array
    {
        $teaserData = $this->arraySerializer->serialize($teaser, $context);
        unset($teaserData['mediaId']);

        $mediaId = $teaser->getMediaId();
        $mediaData = null;
        if ($mediaId) {
            $media = $this->mediaManager->getEntityById($mediaId);
            $mediaData = $this->mediaSerializer->serialize($media, $locale);
        }

        $teaserData['media'] = $mediaData;

        $this->addToReferenceStore($teaser->getId(), $teaser->getType());

        return $teaserData;
    }

    /**
     * @param int|string $id
     */
    private function addToReferenceStore($id, string $alias): void
    {
        if ('pages' === $alias) {
            // unfortunately the reference store for pages was not adjusted and still uses content as alias
            $alias = 'content';
        }

        if ('articles' === $alias) {
            $alias = 'article';
        }

        try {
            $referenceStore = $this->referenceStorePool->getStore($alias);
        } catch (ReferenceStoreNotExistsException $e) {
            // @ignoreException do nothing when reference store was not found

            return;
        }

        $referenceStore->add($id);
    }
}
