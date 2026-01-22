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

use Sulu\Article\Application\Message\ApplyWorkflowTransitionArticleMessage;
use Sulu\Article\Application\Message\CreateArticleMessage;
use Sulu\Article\Domain\Model\Article;
use Sulu\Content\Domain\Model\WorkflowInterface;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\HandledStamp;

trait CreateArticleTrait
{
    /**
     * Create an article with simple data structure.
     *
     * @param array<string, mixed> $data Article data including:
     *                                   - title: string (required)
     *                                   - template: string (optional, defaults to 'default')
     *                                   - published: bool (optional, defaults to true)
     * @param string $locale The locale to create the article in
     */
    protected static function createArticle(
        array $data,
        string $locale = 'de',
    ): Article {
        if (!\array_key_exists('title', $data) || !\is_string($data['title'])) {
            throw new \RuntimeException('Expected a title as string.');
        }

        $messageBus = static::getContainer()->get('sulu_message_bus');

        $articleData = [
            'locale' => $locale,
            'template' => $data['template'] ?? 'default',
            'title' => $data['title'],
        ];

        $reservedKeys = ['title', 'template', 'published'];
        foreach ($data as $key => $value) {
            if (!\in_array($key, $reservedKeys, true)) {
                $articleData[$key] = $value;
            }
        }

        $envelope = $messageBus->dispatch(
            new Envelope(
                new CreateArticleMessage(data: $articleData),
                [new EnableFlushStamp()]
            )
        );

        /** @var HandledStamp[] $handledStamps */
        $handledStamps = $envelope->all(HandledStamp::class);

        /** @var Article $article */
        $article = $handledStamps[0]->getResult();

        if ($data['published'] ?? true) {
            $messageBus->dispatch(
                new Envelope(
                    new ApplyWorkflowTransitionArticleMessage(
                        identifier: ['uuid' => $article->getUuid()],
                        locale: $locale,
                        transitionName: WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH
                    ),
                    [new EnableFlushStamp()]
                )
            );
        }

        return $article;
    }
}
