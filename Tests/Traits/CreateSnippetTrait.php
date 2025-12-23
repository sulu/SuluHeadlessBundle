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

use Doctrine\ORM\EntityManagerInterface;
use Sulu\Content\Domain\Model\WorkflowInterface;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Snippet\Application\Message\ApplyWorkflowTransitionSnippetMessage;
use Sulu\Snippet\Application\Message\CreateSnippetMessage;
use Sulu\Snippet\Domain\Model\Snippet;
use Sulu\Snippet\Domain\Model\SnippetArea;
use Sulu\Snippet\Domain\Model\SnippetInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\HandledStamp;

trait CreateSnippetTrait
{
    /**
     * Create a snippet with simple data structure matching old API.
     *
     * @param array<string, mixed> $data Snippet data including:
     *                                   - title: string (required)
     *                                   - template: string (optional, defaults to 'default')
     *                                   - description: string (optional)
     *                                   - seo: array (optional)
     *                                   - excerpt: array (optional)
     * @param string $locale The locale to create the snippet in
     */
    protected static function createSnippet(
        array $data,
        string $locale = 'de',
    ): Snippet {
        if (!\array_key_exists('title', $data) || !\is_string($data['title'])) {
            throw new \RuntimeException('Expected a title as string.');
        }

        $messageBus = static::getContainer()->get('sulu_message_bus');

        $snippetData = [
            'locale' => $locale,
            'template' => $data['template'] ?? 'default',
            'title' => $data['title'],
        ];

        if (isset($data['seo']) && \is_array($data['seo'])) {
            $snippetData['seo'] = $data['seo'];
        }

        if (isset($data['excerpt']) && \is_array($data['excerpt'])) {
            $excerptData = $data['excerpt'];
            if (isset($excerptData['tags']) && \is_array($excerptData['tags'])) {
                $snippetData['excerptTags'] = $excerptData['tags'];
            }
            if (isset($excerptData['categories']) && \is_array($excerptData['categories'])) {
                $snippetData['excerptCategories'] = $excerptData['categories'];
            }
        }

        $reservedKeys = ['title', 'template', 'seo', 'excerpt'];
        foreach ($data as $key => $value) {
            if (!\in_array($key, $reservedKeys, true)) {
                $snippetData[$key] = $value;
            }
        }

        $envelope = $messageBus->dispatch(
            new Envelope(
                new CreateSnippetMessage(data: $snippetData),
                [new EnableFlushStamp()]
            )
        );

        /** @var HandledStamp[] $handledStamps */
        $handledStamps = $envelope->all(HandledStamp::class);

        /** @var Snippet $snippet */
        $snippet = $handledStamps[0]->getResult();

        $messageBus->dispatch(
            new Envelope(
                new ApplyWorkflowTransitionSnippetMessage(
                    identifier: ['uuid' => $snippet->getUuid()],
                    locale: $locale,
                    transitionName: WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH
                ),
                [new EnableFlushStamp()]
            )
        );

        return $snippet;
    }

    /**
     * Create a snippet area linking a snippet to a webspace.
     */
    protected static function createSnippetArea(
        string $areaKey,
        string $webspaceKey,
        SnippetInterface $snippet,
    ): SnippetArea {
        $entityManager = static::getEntityManager();
        $snippetAreaRepository = static::getContainer()->get('sulu_snippet.snippet_area_repository');

        $existingSnippetArea = $snippetAreaRepository->findOneBy([
            'areaKey' => $areaKey,
            'webspaceKey' => $webspaceKey,
        ]);

        if ($existingSnippetArea instanceof SnippetArea) {
            $existingSnippetArea->setSnippet($snippet);
            $entityManager->flush();

            return $existingSnippetArea;
        }

        $snippetArea = new SnippetArea($areaKey, $webspaceKey);
        $snippetArea->setSnippet($snippet);
        $entityManager->persist($snippetArea);
        $entityManager->flush();

        return $snippetArea;
    }

    abstract protected static function getEntityManager(): EntityManagerInterface;
}
