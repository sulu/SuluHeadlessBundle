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

class NavigationInvalidationSubscriber implements EventSubscriberInterface
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
    }

    public static function getSubscribedEvents()
    {
        return [
            Events::PUBLISH => ['invalidateNavigationContextBeforePublishing', 8192],
            Events::UNPUBLISH => ['invalidateNavigationContextBeforeUnpublishing', 8192],
            Events::REMOVE => ['invalidateNavigationContextBeforeRemoving', 8192],
            Events::REMOVE_LOCALE => ['invalidateNavigationContextBeforeRemovingLocale', 8192],
        ];
    }

    public function invalidateNavigationContextBeforePublishing(PublishEvent $event): void
    {
        $path = $this->documentInspector->getPath($event->getDocument());
        $this->invalidateNavigationContext($path, $event->getLocale());
    }

    public function invalidateNavigationContextBeforeUnpublishing(UnpublishEvent $event): void
    {
        $path = $this->documentInspector->getPath($event->getDocument());
        $this->invalidateNavigationContext($path, $event->getLocale());
    }

    public function invalidateNavigationContextBeforeRemoving(RemoveEvent $event): void
    {
        $document = $event->getDocument();
        $path = $this->documentInspector->getPath($event->getDocument());
        foreach ($this->documentInspector->getLocales($document) as $locale) {
            $this->invalidateNavigationContext($path, $locale);
        }
    }

    public function invalidateNavigationContextBeforeRemovingLocale(RemoveLocaleEvent $event): void
    {
        $path = $this->documentInspector->getPath($event->getDocument());
        $this->invalidateNavigationContext($path, $event->getLocale());
    }

    public function invalidateNavigationContext(string $path, string $locale): void
    {
        if (!$this->cacheManager) {
            return;
        }

        $defaultNode = $this->defaultSession->getNode($path);
        $liveNode = $this->liveSession->getNode($path);

        $propertyName = $this->propertyEncoder->localizedContentName('navContexts', $locale);
        $liveNavigationContexts = [];
        $defaultNavigationContexts = [];
        if ($liveNode->hasProperty($propertyName)) {
            $liveNavigationContexts = $liveNode->getProperty($propertyName)->getValue();
        }
        if ($defaultNode->hasProperty($propertyName)) {
            $defaultNavigationContexts = $defaultNode->getProperty($propertyName)->getValue();
        }

        $navigationContexts = array_unique(array_merge($liveNavigationContexts, $defaultNavigationContexts));

        foreach ($navigationContexts as $navigationContext) {
            $this->cacheManager->invalidateReference('navigation', $navigationContext);
        }
    }
}
