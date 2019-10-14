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

namespace Sulu\Bundle\HeadlessBundle\Content;

use Sulu\Bundle\PageBundle\Document\BasePageDocument;
use Sulu\Component\Content\Compat\Structure\StructureBridge;
use Sulu\Component\Content\Compat\StructureInterface;

class StructureResolver implements StructureResolverInterface
{
    /**
     * @var ContentResolverInterface
     */
    private $contentResolver;

    public function __construct(ContentResolverInterface $contentResolver)
    {
        $this->contentResolver = $contentResolver;
    }

    /**
     * @param StructureBridge $structure
     */
    public function resolve(StructureInterface $structure, string $locale): array
    {
        /** @var BasePageDocument $document */
        $document = $structure->getDocument();

        /** @var \DateTimeInterface $authored */
        $authored = $document->getAuthored();

        $data = [
            'id' => $structure->getUuid(),
            'type' => 'page',
            'template' => $document->getStructureType(),
            'content' => [],
            'view' => [],
            'extension' => [/* TODO extension */],
            'author' => $document->getAuthor(),
            'authored' => $authored->format(\DateTimeImmutable::ISO8601),
            'changer' => $document->getChanger(),
            'changed' => $document->getChanged()->format(\DateTimeImmutable::ISO8601),
            'creator' => $document->getCreator(),
            'created' => $document->getCreated()->format(\DateTimeImmutable::ISO8601),
        ];

        foreach ($structure->getProperties(true) as $property) {
            $contentView = $this->contentResolver->resolve(
                $property->getValue(),
                $property,
                $locale,
                ['webspaceKey' => $structure->getWebspaceKey()]
            );

            $data['content'][$property->getName()] = $contentView->getContent();
            $data['view'][$property->getName()] = $contentView->getView();
        }

        return $data;
    }
}
