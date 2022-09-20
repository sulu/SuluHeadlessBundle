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

use PHPCR\SessionInterface;
use Sulu\Bundle\DocumentManagerBundle\Bridge\DocumentInspector;
use Sulu\Bundle\DocumentManagerBundle\Bridge\PropertyEncoder;
use Sulu\Bundle\HttpCacheBundle\Cache\CacheManager;
use Sulu\Component\DocumentManager\Event\PublishEvent;
use Sulu\Component\DocumentManager\Event\RemoveEvent;
use Sulu\Component\DocumentManager\Event\RemoveLocaleEvent;
use Sulu\Component\DocumentManager\Event\UnpublishEvent;
use Sulu\Component\DocumentManager\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @final
 *
 * @internal
 */
class NavigationInvalidationSubscriber implements EventSubscriberInterface, ResetInterface
{
    /**
     * @var CacheManager|null
     */
    private $cacheManager;

    /**
     * @var DocumentInspector
     */
    private $documentInspector;

    /**
     * @var PropertyEncoder
     */
    private $propertyEncoder;

    /**
     * @var SessionInterface
     */
    private $defaultSession;

    /**
     * @var SessionInterface
     */
    private $liveSession;

    /**
     * @var string[]
     */
    private $navigationContexts;

    public function __construct(
        ?CacheManager $cacheManager,
        PropertyEncoder $propertyEncoder,
        DocumentInspector $documentInspector,
        SessionInterface $defaultSession,
        SessionInterface $liveSession
    ) {
        $this->cacheManager = $cacheManager;
        $this->propertyEncoder = $propertyEncoder;
        $this->documentInspector = $documentInspector;
        $this->defaultSession = $defaultSession;
        $this->liveSession = $liveSession;

        $this->navigationContexts = [];
    }

    public static function getSubscribedEvents()
    {
        return [
            Events::PUBLISH => ['collectNavigationContextBeforePublishing', 8192],
            Events::UNPUBLISH => ['collectNavigationContextBeforeUnpublishing', 8192],
            Events::REMOVE => ['collectNavigationContextBeforeRemoving', 8192],
            Events::REMOVE_LOCALE => ['collectNavigationContextBeforeRemovingLocale', 8192],
            Events::FLUSH => ['invalidateNavigationContexts', -256],
        ];
    }

    public function collectNavigationContextBeforePublishing(PublishEvent $event): void
    {
        $this->collectNavigationContexts($event->getDocument(), $event->getLocale());
    }

    public function collectNavigationContextBeforeUnpublishing(UnpublishEvent $event): void
    {
        $this->collectNavigationContexts($event->getDocument(), $event->getLocale());
    }

    public function collectNavigationContextBeforeRemoving(RemoveEvent $event): void
    {
        $this->collectNavigationContexts($event->getDocument(), null);
    }

    public function collectNavigationContextBeforeRemovingLocale(RemoveLocaleEvent $event): void
    {
        $this->collectNavigationContexts($event->getDocument(), $event->getLocale());
    }

    private function collectNavigationContexts(object $document, ?string $eventLocale): void
    {
        $path = $this->documentInspector->getPath($document);
        $locales = $eventLocale ? [$eventLocale] : $this->documentInspector->getLocales($document);

        $defaultNode = $this->defaultSession->getNode($path);
        $liveNode = $this->liveSession->getNode($path);

        foreach ($locales as $locale) {
            $propertyName = $this->propertyEncoder->localizedContentName('navContexts', $locale);
            $liveNavigationContexts = [];
            $defaultNavigationContexts = [];

            if ($liveNode->hasProperty($propertyName)) {
                $liveNavigationContexts = $liveNode->getProperty($propertyName)->getValue();
            }

            if ($defaultNode->hasProperty($propertyName)) {
                $defaultNavigationContexts = $defaultNode->getProperty($propertyName)->getValue();
            }

            $this->navigationContexts = \array_unique(
                \array_merge(
                    $this->navigationContexts,
                    $liveNavigationContexts,
                    $defaultNavigationContexts
                )
            );
        }
    }

    public function invalidateNavigationContexts(): void
    {
        if (!$this->cacheManager) {
            return;
        }

        foreach ($this->navigationContexts as $navigationContext) {
            $this->cacheManager->invalidateReference('navigation', $navigationContext);
        }
    }

    public function reset(): void
    {
        $this->navigationContexts = [];
    }
}
