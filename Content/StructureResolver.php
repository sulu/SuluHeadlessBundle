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

        /** @var int|null $author */
        $author = null;
        if (method_exists($document, 'getAuthor')) {
            $author = $document->getAuthor();
        }

        /** @var \DateTimeInterface|null $authored */
        $authored = null;
        if (method_exists($document, 'getAuthored')) {
            /** @var \DateTimeInterface|null $authored typehint in sulu is wrong */
            $authored = $document->getAuthored();
        }

        /** @var int|null $changer */
        $changer = null;
        if (method_exists($document, 'getChanger')) {
            $changer = $document->getChanger();
        }

        /** @var \DateTimeInterface|null $changed */
        $changed = null;
        if (method_exists($document, 'getChanged')) {
            $changed = $document->getChanged();
        }

        /** @var int|null $creator */
        $creator = null;
        if (method_exists($document, 'getCreator')) {
            $creator = $document->getCreator();
        }

        /** @var \DateTimeInterface|null $created */
        $created = null;
        if (method_exists($document, 'getCreated')) {
            $created = $document->getCreated();
        }

        $type = 'page';
        if (method_exists($structure, 'getContent') && method_exists($structure->getContent(), 'getTemplateType')) {
            // used for content-bundle to determine current template type
            $type = $structure->getContent()->getTemplateType();
        }

        $data = [
            'id' => $structure->getUuid(),
            'type' => $type,
            'template' => $document->getStructureType(),
            'content' => [],
            'view' => [],
            'extension' => [/* TODO extension */],
            'author' => $author,
            'authored' => $authored ? $authored->format(\DateTimeImmutable::ISO8601) : null,
            'changer' => $changer,
            'changed' => $changed ? $changed->format(\DateTimeImmutable::ISO8601) : null,
            'creator' => $creator,
            'created' => $created ? $created->format(\DateTimeImmutable::ISO8601) : null,
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
