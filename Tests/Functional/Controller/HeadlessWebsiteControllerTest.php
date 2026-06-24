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

namespace Sulu\Bundle\HeadlessBundle\Tests\Functional\Controller;

use Sulu\Bundle\HeadlessBundle\Tests\Functional\BaseTestCase;
use Sulu\Bundle\HeadlessBundle\Tests\Traits\CreatePageTrait;
use Sulu\Content\Domain\Model\WorkflowInterface;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Page\Application\Message\ApplyWorkflowTransitionPageMessage;
use Sulu\Page\Application\Message\ModifyPageMessage;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;

class HeadlessWebsiteControllerTest extends BaseTestCase
{
    use CreatePageTrait;

    private KernelBrowser $websiteClient;

    public static function setUpBeforeClass(): void
    {
        static::purgeDatabase();
        self::bootKernel();

        self::createPage([
            'title' => 'Test',
            'url' => '/test',
            'seo' => [
                'description' => 'seo-description',
            ],
            'excerpt' => [
                'title' => 'excerpt-title',
            ],
        ]);

        $bothLocalesPage = self::createPage([
            'title' => 'Both Locales',
            'url' => '/both-locales',
        ]);

        $messageBus = static::getContainer()->get('sulu_message_bus');

        $messageBus->dispatch(
            new Envelope(
                new ModifyPageMessage(
                    ['uuid' => $bothLocalesPage->getUuid()],
                    [
                        'locale' => 'en',
                        'template' => 'default',
                        'title' => 'Both Locales EN',
                        'url' => '/both-locales',
                    ]
                ),
                [new EnableFlushStamp()]
            )
        );

        $messageBus->dispatch(
            new Envelope(
                new ApplyWorkflowTransitionPageMessage(
                    identifier: ['uuid' => $bothLocalesPage->getUuid()],
                    locale: 'en',
                    transitionName: WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH
                ),
                [new EnableFlushStamp()]
            )
        );

        // Clear entity manager to ensure fresh state for routing
        self::getEntityManager()->clear();

        static::ensureKernelShutdown();
    }

    protected function setUp(): void
    {
        $this->websiteClient = $this->createWebsiteClient();
    }

    public function testIndexAction(): void
    {
        $this->websiteClient->request('GET', '/test.json');

        $response = $this->websiteClient->getResponse();

        $this->assertResponseContent(
            'headless_website__test_index.json',
            $response,
            Response::HTTP_OK
        );
    }

    public function testIndexHtmlAction(): void
    {
        $this->websiteClient->request('GET', '/test');

        $response = $this->websiteClient->getResponse();
        $this->assertInstanceOf(Response::class, $response);

        $content = $response->getContent();
        $this->assertIsString($content);

        $this->assertStringContainsString('window.SULU_HEADLESS_VIEW_DATA =', $content);

        $jsonContent = \str_replace([
            '<script>window.SULU_HEADLESS_VIEW_DATA = ',
            ';</script>',
        ], '', $content);

        // Replace HTML response content with Json to match if the same data is set to the template.
        $response->setContent($jsonContent);

        $this->assertInstanceOf(Response::class, $response);

        $this->assertResponseContent(
            'headless_website__test_index.json',
            $response,
            Response::HTTP_OK
        );
    }

    public function testIndexActionLocalizationsAlternateTrueWhenBothLocalesExist(): void
    {
        $this->websiteClient->request('GET', '/both-locales.json');

        $response = $this->websiteClient->getResponse();

        $this->assertResponseContent(
            'headless_website__both_locales.json',
            $response,
            Response::HTTP_OK
        );
    }
}
