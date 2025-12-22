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

use Sulu\Bundle\HttpCacheBundle\Cache\CacheManagerInterface;
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Domain\Exception\ContentNotFoundException;
use Sulu\Content\Domain\Model\WorkflowInterface;
use Sulu\Page\Domain\Event\PageRemovedEvent;
use Sulu\Page\Domain\Event\PageWorkflowTransitionAppliedEvent;
use Sulu\Page\Domain\Model\PageDimensionContentInterface;
use Sulu\Page\Domain\Model\PageInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class NavigationInvalidationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ?CacheManagerInterface $cacheManager,
        private ContentAggregatorInterface $contentAggregator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PageWorkflowTransitionAppliedEvent::class => 'onWorkflowTransition',
            PageRemovedEvent::class => 'onPageRemoved',
        ];
    }

    public function onWorkflowTransition(PageWorkflowTransitionAppliedEvent $event): void
    {
        if (!$this->cacheManager) {
            return;
        }

        if (!\in_array($event->getWorkflowTransitionName(), [
            WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH,
            WorkflowInterface::WORKFLOW_TRANSITION_UNPUBLISH,
        ], true)) {
            return;
        }

        $page = $event->getPage();
        $locale = $event->getResourceLocale();

        $navigationContexts = $this->collectNavigationContexts($page, $locale);

        foreach ($navigationContexts as $context) {
            $this->cacheManager->invalidateReference('navigation', $context);
        }
    }

    public function onPageRemoved(PageRemovedEvent $event): void
    {
        if (!$this->cacheManager) {
            return;
        }

        $context = $event->getEventContext();
        /** @var string[] $navigationContexts */
        $navigationContexts = $context['navigationContexts'] ?? [];

        foreach ($navigationContexts as $navigationContext) {
            $this->cacheManager->invalidateReference('navigation', $navigationContext);
        }
    }

    /**
     * Collect navigation contexts from both draft and live stages.
     *
     * @return string[]
     */
    private function collectNavigationContexts(PageInterface $page, ?string $locale): array
    {
        if (!$locale) {
            return [];
        }

        $navigationContexts = [];

        // Collect from draft stage
        try {
            /** @var PageDimensionContentInterface $draftContent */
            $draftContent = $this->contentAggregator->aggregate($page, [
                'locale' => $locale,
                'stage' => PageDimensionContentInterface::STAGE_DRAFT,
            ]);
            $navigationContexts = \array_merge($navigationContexts, $draftContent->getNavigationContexts());
        } catch (ContentNotFoundException) {
            // @ignoreException
            // Page may not have draft content
        }

        // Collect from live stage
        try {
            /** @var PageDimensionContentInterface $liveContent */
            $liveContent = $this->contentAggregator->aggregate($page, [
                'locale' => $locale,
                'stage' => PageDimensionContentInterface::STAGE_LIVE,
            ]);
            $navigationContexts = \array_merge($navigationContexts, $liveContent->getNavigationContexts());
        } catch (ContentNotFoundException) {
            // @ignoreException
            // Page may not have live content
        }

        return \array_unique($navigationContexts);
    }
}
