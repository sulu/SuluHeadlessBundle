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
use Sulu\Bundle\HttpCacheBundle\ReferenceStore\ReferenceStoreInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

class SnippetAreaControllerTest extends BaseTestCase
{
    use CreateSnippetTrait;

    private KernelBrowser $websiteClient;

    private ?ReferenceStoreInterface $snippetAreaReferenceStore = null;

    public static function setUpBeforeClass(): void
    {
        static::purgeDatabase();
        self::bootKernel();

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

        self::createSnippetArea('default', 'sulu_io', $snippet);

        self::createSnippet([
            'title' => 'My other Snippet',
            'template' => 'other',
        ], 'de');

        $currentTime = new \DateTime();
        $scheduledBlockEndtime = (new \DateTime())->add(new \DateInterval('PT30M'));
        $snippetWithBlocks = self::createSnippet(
            [
                'title' => 'My Snippet with blocks',
                'description' => 'Description of my snippet with blocks',
                'template' => 'default-blocks',
                'blocks' => [
                    [
                        'type' => 'editor_image',
                        'article' => '<p>Article text</p>',
                        'settings' => [
                            'schedules_enabled' => true,
                            'schedules' => [
                                [
                                    'type' => 'weekly',
                                    'days' => [
                                        'monday',
                                        'tuesday',
                                        'wednesday',
                                        'thursday',
                                        'friday',
                                        'saturday',
                                        'sunday',
                                    ],
                                    'start' => $currentTime->format('H:i:s'),
                                    'end' => $scheduledBlockEndtime->format('H:i:s'),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'de'
        );
        self::createSnippetArea('default-blocks', 'sulu_io', $snippetWithBlocks);

        // Clear entity manager to ensure fresh state
        self::getEntityManager()->clear();

        static::ensureKernelShutdown();
    }

    protected function setUp(): void
    {
        $this->websiteClient = $this->createWebsiteClient();

        /** @var ReferenceStoreInterface|null $snippetAreaReferenceStore */
        $snippetAreaReferenceStore = self::getContainer()->get('sulu_snippet.reference_store.snippet_area', ContainerInterface::NULL_ON_INVALID_REFERENCE);
        $this->snippetAreaReferenceStore = $snippetAreaReferenceStore;
    }

    /**
     * @return \Generator<mixed[]>
     */
    public static function provideAttributes(): \Generator
    {
        yield [
            '/api/snippet-areas/default',
            Response::HTTP_OK,
            'snippet-area__default.json',
            null,
            86400,
        ];

        yield [
            '/api/snippet-areas/default?includeExtension=true',
            Response::HTTP_OK,
            'snippet-area__default_include-extension.json',
            null,
            86400,
        ];

        yield [
            '/api/snippet-areas/default?includeExtension=false',
            Response::HTTP_OK,
            'snippet-area__default.json',
            null,
            86400,
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
            'No snippet found for snippet area "invalid"',
        ];

        yield [
            '/api/snippet-areas/default-blocks',
            Response::HTTP_OK,
            null,
            null,
            86400,
        ];
    }

    /**
     * @dataProvider provideAttributes
     */
    public function testGetAction(
        string $url,
        int $statusCode = Response::HTTP_OK,
        ?string $expectedPatternFile = null,
        ?string $errorMessage = null,
        ?int $reverseProxyTtl = null,
    ): void {
        $this->websiteClient->request('GET', $url);

        $response = $this->websiteClient->getResponse();
        self::assertInstanceOf(Response::class, $response);

        if (200 === $response->getStatusCode()) {
            $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));

            if ($this->snippetAreaReferenceStore) {
                $this->assertStringContainsString('snippet_area-default', (string) $response->headers->get('x-cache-tags'));
            }
        }

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

            /** @var \stdClass|false $responseObject */
            $responseObject = \json_decode($content);
            self::assertNotFalse($responseObject);

            self::assertTrue(\property_exists($responseObject, 'message'));
            self::assertSame($errorMessage, $responseObject->message);
        }

        if (null !== $reverseProxyTtl) {
            // we need to use less than because the reverse proxy ttl is calculated during runtime
            self::assertLessThanOrEqual($reverseProxyTtl, (int) $response->headers->get('X-Reverse-Proxy-TTL'));
        }
    }
}
