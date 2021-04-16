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
use Sulu\Bundle\HeadlessBundle\Tests\Traits\CreateSnippetTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

class SnippetAreaControllerTest extends BaseTestCase
{
    use CreateSnippetTrait;

    /**
     * @var KernelBrowser
     */
    private $websiteClient;

    public static function setUpBeforeClass(): void
    {
        self::initPhpcr();

        $snippet = self::createSnippet([
            'title' => 'My Snippet',
            'description' => 'Description of my snippet',
            'excerpt' => [
                'tags' => [
                    'tag1',
                    'tag2',
                ],
            ],
            'template' => 'default',
        ], 'de');

        $defaultSnippetManager = self::$container->get('sulu_snippet.default_snippet.manager');
        $defaultSnippetManager->save(
            'sulu_io',
            'default',
            $snippet->getUuid(),
            'de',
        );

        self::createSnippet([
            'title' => 'My other Snippet',
            'template' => 'other',
        ], 'de');

        static::ensureKernelShutdown();
    }

    protected function setUp(): void
    {
        $this->websiteClient = $this->createWebsiteClient();
    }

    /**
     * @return \Generator<mixed[]>
     */
    public function provideAttributes(): \Generator
    {
        yield [
            '/api/snippet-areas/default',
            Response::HTTP_OK,
            'snippet-area__default.json',
        ];

        yield [
            '/api/snippet-areas/default?includeExtension=true',
            Response::HTTP_OK,
            'snippet-area__default_include-extension.json',
        ];

        yield [
            '/api/snippet-areas/default?includeExtension=false',
            Response::HTTP_OK,
            'snippet-area__default.json',
        ];

        yield [
            '/en/api/snippet-areas/default',
            Response::HTTP_NOT_FOUND,
            null,
            'Snippet for snippet area "default" does not exist in locale "en"',
        ];

        yield [
            '/api/snippet-areas/other',
            Response::HTTP_NOT_FOUND,
            null,
            'No snippet found for snippet area "other"',
        ];

        yield [
            '/api/snippet-areas/invalid',
            Response::HTTP_NOT_FOUND,
            null,
            'Snippet area "invalid" does not exist',
        ];
    }

    /**
     * @dataProvider provideAttributes
     */
    public function testGetAction(
        string $url,
        int $statusCode = Response::HTTP_OK,
        ?string $expectedPatternFile = null,
        ?string $errorMessage = null
    ): void {
        $this->websiteClient->request('GET', $url);

        $response = $this->websiteClient->getResponse();
        self::assertInstanceOf(Response::class, $response);

        if (null !== $expectedPatternFile) {
            self::assertResponseContent(
                $expectedPatternFile,
                $response,
                $statusCode
            );
        }

        if (null !== $errorMessage) {
            self::assertSame($statusCode, $response->getStatusCode());

            $content = $response->getContent();
            self::assertIsString($content);

            $responseObject = json_decode($content);
            self::assertNotFalse($responseObject);

            self::assertObjectHasAttribute('message', $responseObject);
            self::assertSame($errorMessage, $responseObject->message);
        }
    }
}
