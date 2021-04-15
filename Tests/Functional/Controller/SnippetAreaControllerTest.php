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

use Sulu\Bundle\HeadlessBundle\Tests\Application\Testing\HeadlessBundleKernelBrowser;
use Sulu\Bundle\HeadlessBundle\Tests\Functional\BaseTestCase;
use Sulu\Bundle\HeadlessBundle\Tests\Traits\CreateSnippetTrait;
use Symfony\Component\HttpFoundation\Response;

class SnippetAreaControllerTest extends BaseTestCase
{
    use CreateSnippetTrait;

    /**
     * @var HeadlessBundleKernelBrowser
     */
    private $websiteClient;

    public static function setUpBeforeClass(): void
    {
        self::initPhpcr();

        $snippet = self::createSnippet([
            'title' => 'My Snippet',
            'description' => 'Description of my snippet',
            'ext' => [
                'excerpt' => [
                    'tags' => [
                        'tag1',
                        'tag2',
                    ],
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
        /** @var HeadlessBundleKernelBrowser $websiteClient */
        $websiteClient = $this->createWebsiteClient();

        $this->websiteClient = $websiteClient;
    }

    /**
     * @return \Generator<mixed[]>
     */
    public function provideAttributes(): \Generator
    {
        yield [
            'default',
            [],
            Response::HTTP_OK,
            'snippet-area__default.json',
        ];

        yield [
            'default',
            [
                'includeExtension' => 'true',
            ],
            Response::HTTP_OK,
            'snippet-area__default_include-extension.json',
        ];

        yield [
            'default',
            [
                'includeExtension' => 'false',
            ],
            Response::HTTP_OK,
            'snippet-area__default.json',
        ];

        yield [
            'other',
            [],
            Response::HTTP_NOT_FOUND,
            null,
            'No snippet found for snippet area "other"',
        ];

        yield [
            'invalid',
            [],
            Response::HTTP_NOT_FOUND,
            null,
            'Snippet area "invalid" does not exist',
        ];
    }

    /**
     * @param mixed[] $filters
     *
     * @dataProvider provideAttributes
     */
    public function testGetAction(
        string $area,
        array $filters = [],
        int $statusCode = Response::HTTP_OK,
        ?string $expectedPatternFile = null,
        ?string $errorMessage = null
    ): void {
        $this->websiteClient->jsonRequest('GET', '/api/snippet-areas/' . $area . '?' . http_build_query($filters));

        $response = $this->websiteClient->getResponse();
        $this->assertInstanceOf(Response::class, $response);

        if (null !== $expectedPatternFile) {
            $this->assertResponseContent(
                $expectedPatternFile,
                $response,
                $statusCode
            );
        }

        if (null !== $errorMessage) {
            $this->assertSame($statusCode, $response->getStatusCode());

            $responseObject = json_decode($response->getContent() ?: '{}');
            $this->assertObjectHasAttribute('message', $responseObject);
            $this->assertSame($errorMessage, $responseObject->message);
        }
    }
}
