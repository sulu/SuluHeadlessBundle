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

namespace Sulu\Bundle\HeadlessBundle\EventSubscriber;

use Sulu\Bundle\HttpCacheBundle\Cache\CacheManager;
use Sulu\Bundle\SnippetBundle\Domain\Event\WebspaceDefaultSnippetModifiedEvent;
use Sulu\Bundle\SnippetBundle\Domain\Event\WebspaceDefaultSnippetRemovedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @final
 *
 * @internal
 */
class SnippetAreaInvalidationSubscriber implements EventSubscriberInterface
{
    /**
     * @var CacheManager|null
     */
    private $cacheManager;

    public function __construct(?CacheManager $cacheManager)
    {
        $this->cacheManager = $cacheManager;
    }

    public static function getSubscribedEvents()
    {
        return [
            WebspaceDefaultSnippetModifiedEvent::class => 'invalidateSnippetAreaOnModified',
            WebspaceDefaultSnippetRemovedEvent::class => 'invalidateSnippetAreaOnRemoved',
        ];
    }

    public function invalidateSnippetAreaOnModified(WebspaceDefaultSnippetModifiedEvent $event): void
    {
        $this->invalidateSnippetArea($event->getSnippetAreaKey());
    }

    public function invalidateSnippetAreaOnRemoved(WebspaceDefaultSnippetRemovedEvent $event): void
    {
        $this->invalidateSnippetArea($event->getSnippetAreaKey());
    }

    private function invalidateSnippetArea(string $snippetAreaKey): void
    {
        if (!$this->cacheManager) {
            return;
        }

        $this->cacheManager->invalidateReference('snippet_area', $snippetAreaKey);
    }
}
