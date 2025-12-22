<?php

declare(strict_types=1);

namespace Sulu\Bundle\HeadlessBundle\Tests\Unit\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\HeadlessBundle\EventSubscriber\NavigationInvalidationSubscriber;
use Sulu\Bundle\HttpCacheBundle\Cache\CacheManagerInterface;
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Domain\Exception\ContentNotFoundException;
use Sulu\Content\Domain\Model\WorkflowInterface;
use Sulu\Page\Domain\Event\PageRemovedEvent;
use Sulu\Page\Domain\Event\PageWorkflowTransitionAppliedEvent;
use Sulu\Page\Domain\Model\Page;
use Sulu\Page\Domain\Model\PageDimensionContent;
use Sulu\Page\Domain\Model\PageDimensionContentInterface;

class NavigationInvalidationSubscriberTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<CacheManagerInterface> */
    private ObjectProphecy $cacheManager;
    /** @var ObjectProphecy<ContentAggregatorInterface> */
    private ObjectProphecy $contentAggregator;
    private NavigationInvalidationSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->cacheManager = $this->prophesize(CacheManagerInterface::class);
        $this->contentAggregator = $this->prophesize(ContentAggregatorInterface::class);

        $this->subscriber = new NavigationInvalidationSubscriber(
            $this->cacheManager->reveal(),
            $this->contentAggregator->reveal()
        );
    }

    public function testGetSubscribedEvents(): void
    {
        $events = NavigationInvalidationSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(PageWorkflowTransitionAppliedEvent::class, $events);
        $this->assertArrayHasKey(PageRemovedEvent::class, $events);
    }

    public function testInvalidateNavigationContextsOnPublish(): void
    {
        $page = new Page('page-uuid-123');
        $page->setWebspaceKey('sulu_io');

        $draftContent = new PageDimensionContent($page);
        $draftContent->setNavigationContexts(['main', 'footer']);

        $liveContent = new PageDimensionContent($page);
        $liveContent->setNavigationContexts(['main']);

        $event = new PageWorkflowTransitionAppliedEvent(
            $page,
            WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH,
            'en'
        );

        $this->contentAggregator->aggregate($page, [
            'locale' => 'en',
            'stage' => PageDimensionContentInterface::STAGE_DRAFT,
        ])->willReturn($draftContent);

        $this->contentAggregator->aggregate($page, [
            'locale' => 'en',
            'stage' => PageDimensionContentInterface::STAGE_LIVE,
        ])->willReturn($liveContent);

        $this->cacheManager->invalidateReference('navigation', 'main')
            ->shouldBeCalled();
        $this->cacheManager->invalidateReference('navigation', 'footer')
            ->shouldBeCalled();

        $this->subscriber->onWorkflowTransition($event);
    }

    public function testDoesNotInvalidateOnNonPublishTransition(): void
    {
        $page = new Page('page-uuid-999');
        $page->setWebspaceKey('sulu_io');

        $event = new PageWorkflowTransitionAppliedEvent(
            $page,
            'request_for_review',
            'en'
        );

        $this->cacheManager->invalidateReference()->shouldNotBeCalled();

        $this->subscriber->onWorkflowTransition($event);
    }

    public function testInvalidateNavigationContextsOnUnpublish(): void
    {
        $page = new Page('page-uuid-456');
        $page->setWebspaceKey('sulu_io');

        $liveContent = new PageDimensionContent($page);
        $liveContent->setNavigationContexts(['main', 'sidebar']);

        $event = new PageWorkflowTransitionAppliedEvent(
            $page,
            WorkflowInterface::WORKFLOW_TRANSITION_UNPUBLISH,
            'en'
        );

        $this->contentAggregator->aggregate($page, [
            'locale' => 'en',
            'stage' => PageDimensionContentInterface::STAGE_DRAFT,
        ])->willThrow(ContentNotFoundException::class);

        $this->contentAggregator->aggregate($page, [
            'locale' => 'en',
            'stage' => PageDimensionContentInterface::STAGE_LIVE,
        ])->willReturn($liveContent);

        $this->cacheManager->invalidateReference('navigation', 'main')
            ->shouldBeCalled();
        $this->cacheManager->invalidateReference('navigation', 'sidebar')
            ->shouldBeCalled();

        $this->subscriber->onWorkflowTransition($event);
    }

    public function testInvalidateNavigationContextsOnRemove(): void
    {
        $event = new PageRemovedEvent(
            'page-uuid-789',
            'sulu_io',
            'Test Page',
            [
                'locales' => ['en', 'de'],
                'navigationContexts' => ['main', 'footer'],
            ]
        );

        $this->cacheManager->invalidateReference('navigation', 'main')
            ->shouldBeCalled();
        $this->cacheManager->invalidateReference('navigation', 'footer')
            ->shouldBeCalled();

        $this->subscriber->onPageRemoved($event);
    }

    public function testDoesNothingWithNoCacheManager(): void
    {
        $subscriber = new NavigationInvalidationSubscriber(
            null,
            $this->contentAggregator->reveal()
        );

        $page = new Page('page-uuid-123');
        $page->setWebspaceKey('sulu_io');

        $event = new PageWorkflowTransitionAppliedEvent(
            $page,
            WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH,
            'en'
        );

        $this->contentAggregator->aggregate()->shouldNotBeCalled();

        $subscriber->onWorkflowTransition($event);
    }

    public function testHandlesContentNotFoundException(): void
    {
        $page = new Page('page-uuid-new');
        $page->setWebspaceKey('sulu_io');

        $event = new PageWorkflowTransitionAppliedEvent(
            $page,
            WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH,
            'en'
        );

        $this->contentAggregator->aggregate($page, [
            'locale' => 'en',
            'stage' => PageDimensionContentInterface::STAGE_DRAFT,
        ])->willThrow(ContentNotFoundException::class);

        $this->contentAggregator->aggregate($page, [
            'locale' => 'en',
            'stage' => PageDimensionContentInterface::STAGE_LIVE,
        ])->willThrow(ContentNotFoundException::class);

        $this->cacheManager->invalidateReference()->shouldNotBeCalled();

        $this->subscriber->onWorkflowTransition($event);
    }
}
