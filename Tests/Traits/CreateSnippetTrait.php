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

namespace Sulu\Bundle\HeadlessBundle\Tests\Traits;

use Sulu\Bundle\SnippetBundle\Document\SnippetDocument;
use Sulu\Component\DocumentManager\DocumentManagerInterface;

trait CreateSnippetTrait
{
    /**
     * @param mixed[] $data
     */
    private static function createSnippet(
        array $data,
        string $locale = 'de'
    ): SnippetDocument {
        /** @var DocumentManagerInterface $documentManager */
        $documentManager = static::getContainer()->get('sulu_document_manager.document_manager');

        /** @var SnippetDocument $document */
        $document = $documentManager->create('snippet');

        if (!$document instanceof SnippetDocument) {
            throw new \RuntimeException('Invalid document');
        }

        if (!\array_key_exists('title', $data) || !\is_string($data['title'])) {
            throw new \RuntimeException('Expected a title as string is given.');
        }

        $extensionData = [
            'seo' => $data['seo'] ?? [],
            'excerpt' => $data['excerpt'] ?? [],
        ];
        unset($data['excerpt']);
        unset($data['seo']);

        $document->setLocale($locale);
        $document->setTitle($data['title']);
        $document->setStructureType($data['template'] ?? 'default');
        $document->setExtensionsData($extensionData);

        $document->getStructure()->bind($data);

        $documentManager->persist($document, $locale);

        $documentManager->publish($document, $locale);
        $documentManager->flush();

        return $document;
    }
}
