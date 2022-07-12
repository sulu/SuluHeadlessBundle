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

use Sulu\Bundle\PageBundle\Document\PageDocument;
use Sulu\Component\Content\Document\WorkflowStage;
use Sulu\Component\DocumentManager\DocumentManagerInterface;

trait CreatePageTrait
{
    /**
     * @param mixed[] $data
     */
    private static function createPage(
        array $data,
        string $locale = 'de',
        string $parentPath = '/cmf/sulu_io/contents'
    ): PageDocument {
        /** @var DocumentManagerInterface $documentManager */
        $documentManager = static::getContainer()->get('sulu_document_manager.document_manager');

        /** @var PageDocument $document */
        $document = $documentManager->create('page');

        if (!$document instanceof PageDocument) {
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
        $document->setResourceSegment($data['url'] ?? '/' . \strtolower($data['title']));
        $document->setExtensionsData($extensionData);

        if ($data['published'] ?? true) {
            $document->setWorkflowStage(WorkflowStage::PUBLISHED);
        }

        $document->setNavigationContexts($data['navigationContexts'] ?? []);

        $document->getStructure()->bind($data);

        $documentManager->persist($document, $locale, [
            'parent_path' => $data['parent_path'] ?? '/cmf/sulu_io/contents',
        ]);

        if ($data['published'] ?? true) {
            $documentManager->publish($document, $locale);
        }

        $documentManager->flush();

        return $document;
    }
}
