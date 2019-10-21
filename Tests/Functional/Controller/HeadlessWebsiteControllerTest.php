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
use Symfony\Component\HttpFoundation\Response;

class HeadlessWebsiteControllerTest extends BaseTestCase
{
    use CreatePageTrait;

    public static function setUpBeforeClass(): void
    {
        self::initPhpcr();

        self::createPage([
            'title' => 'Test',
            'url' => '/test',
        ]);
    }

    public function testIndexAction(): void
    {
        $websiteClient = $this->createWebsiteClient();
        $websiteClient->request('GET', '/test.json');

        $response = $websiteClient->getResponse();

        $this->assertResponseContent(
            'headless_website__test_index.json',
            $response,
            Response::HTTP_OK
        );
    }

    public function testIndexHtmlAction(): void
    {
        $websiteClient = $this->createWebsiteClient();
        $websiteClient->request('GET', '/test');

        $response = $websiteClient->getResponse();
        $this->assertInstanceOf(Response::class, $response);

        $content = $response->getContent();
        $this->assertIsString($content);

        $this->assertStringContainsString('window.SITE_DATA =', $content);

        $jsonContent = str_replace([
            '<script>window.SITE_DATA = ',
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
