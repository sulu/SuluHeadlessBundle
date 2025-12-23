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

use Sulu\Content\Domain\Model\WorkflowInterface;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Page\Application\Message\ApplyWorkflowTransitionPageMessage;
use Sulu\Page\Application\Message\CreatePageMessage;
use Sulu\Page\Application\MessageHandler\CreatePageMessageHandler;
use Sulu\Page\Domain\Model\Page;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\HandledStamp;

trait CreatePageTrait
{
    /**
     * Create a page with simple data structure matching old API.
     *
     * @param array<string, mixed> $data Page data including:
     *                                   - title: string (required)
     *                                   - url: string (optional, defaults to /title)
     *                                   - template: string (optional, defaults to 'default')
     *                                   - seo: array (optional)
     *                                   - excerpt: array (optional)
     *                                   - navigationContexts: array (optional)
     *                                   - published: bool (optional, defaults to true)
     *                                   - parentId: string (optional, defaults to homepage)
     * @param string $locale The locale to create the page in
     * @param string $webspaceKey The webspace key
     */
    protected static function createPage(
        array $data,
        string $locale = 'de',
        string $webspaceKey = 'sulu_io',
    ): Page {
        if (!\array_key_exists('title', $data) || !\is_string($data['title'])) {
            throw new \RuntimeException('Expected a title as string.');
        }

        $messageBus = static::getContainer()->get('sulu_message_bus');

        $pageData = [
            'locale' => $locale,
            'template' => $data['template'] ?? 'default',
            'title' => $data['title'],
            'url' => $data['url'] ?? '/' . \strtolower($data['title']),
        ];

        if (isset($data['seo']) && \is_array($data['seo'])) {
            $pageData['seo'] = $data['seo'];
        }

        if (isset($data['excerpt']) && \is_array($data['excerpt'])) {
            $pageData['excerpt'] = $data['excerpt'];
        }

        if (isset($data['navigationContexts']) && \is_array($data['navigationContexts'])) {
            $pageData['navigationContexts'] = $data['navigationContexts'];
        }

        $reservedKeys = ['title', 'url', 'template', 'seo', 'excerpt', 'navigationContexts', 'published', 'parentId'];
        foreach ($data as $key => $value) {
            if (!\in_array($key, $reservedKeys, true)) {
                $pageData[$key] = $value;
            }
        }

        /** @var string $parentId */
        $parentId = $data['parentId'] ?? CreatePageMessageHandler::HOMEPAGE_PARENT_ID;

        $envelope = $messageBus->dispatch(
            new Envelope(
                new CreatePageMessage(
                    webspaceKey: $webspaceKey,
                    parentId: $parentId,
                    data: $pageData
                ),
                [new EnableFlushStamp()]
            )
        );

        /** @var HandledStamp[] $handledStamps */
        $handledStamps = $envelope->all(HandledStamp::class);

        /** @var Page $page */
        $page = $handledStamps[0]->getResult();

        if ($data['published'] ?? true) {
            $messageBus->dispatch(
                new Envelope(
                    new ApplyWorkflowTransitionPageMessage(
                        identifier: ['uuid' => $page->getUuid()],
                        locale: $locale,
                        transitionName: WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH
                    ),
                    [new EnableFlushStamp()]
                )
            );
        }

        return $page;
    }
}
