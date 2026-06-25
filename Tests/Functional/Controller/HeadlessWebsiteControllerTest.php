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
use Sulu\Component\Content\Document\WorkflowStage;
use Sulu\Component\DocumentManager\DocumentManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

class HeadlessWebsiteControllerTest extends BaseTestCase
{
    use CreatePageTrait;

    /**
     * @var KernelBrowser
     */
    private $websiteClient;

    public static function setUpBeforeClass(): void
    {
        self::initPhpcr();

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

        $bothLocalesDocument = self::createPage([
            'title' => 'Beide Locales',
            'url' => '/beide-locales',
        ]);

        /** @var DocumentManagerInterface $documentManager */
        $documentManager = static::getContainer()->get('sulu_document_manager.document_manager');

        /** @var \Sulu\Bundle\PageBundle\Document\PageDocument $enDocument */
        $enDocument = $documentManager->find($bothLocalesDocument->getPath(), 'en');
        $enDocument->setTitle('Both Locales EN');
        $enDocument->setResourceSegment('/both-locales');
        $enDocument->setWorkflowStage(WorkflowStage::PUBLISHED);
        $documentManager->persist($enDocument, 'en', ['parent_path' => '/cmf/sulu_io/contents']);
        $documentManager->publish($enDocument, 'en');
        $documentManager->flush();

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

    public function testIndexActionLocalizationsAlternateTrueWhenBothLocalesExist(): void
    {
        $this->websiteClient->request('GET', '/beide-locales.json');

        $response = $this->websiteClient->getResponse();

        $this->assertResponseContent(
            'headless_website__both_locales.json',
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
}
