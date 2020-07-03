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
use Sulu\Bundle\HeadlessBundle\Tests\Traits\CreateMediaTrait;
use Sulu\Bundle\HeadlessBundle\Tests\Traits\CreatePageTrait;
use Sulu\Bundle\MediaBundle\DataFixtures\ORM\LoadCollectionTypes;
use Sulu\Bundle\MediaBundle\DataFixtures\ORM\LoadMediaTypes;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

class NavigationControllerTest extends BaseTestCase
{
    use CreatePageTrait;
    use CreateMediaTrait;

    /**
     * @var KernelBrowser
     */
    private $websiteClient;

    /**
     * @var string
     */
    private static $page1Uuid;

    public static function setUpBeforeClass(): void
    {
        self::initPhpcr();

        // create collection types and media types
        $collectionTypeFixture = new LoadCollectionTypes();
        $collectionTypeFixture->load(self::getEntityManager());
        $mediaTypeFixture = new LoadMediaTypes();
        $mediaTypeFixture->load(self::getEntityManager());

        $collection = self::createCollection('Test Collection', 'de');
        $media = self::createMedia('Test Image', $collection, 'de');
        self::getEntityManager()->flush();

        self::$page1Uuid = self::createPage([
            'title' => 'Test 1',
            'url' => '/test-1',
            'navigationContexts' => [
                'main',
            ],
        ])->getUuid();

        self::createPage([
            'title' => 'Test 2',
            'url' => '/test-2',
            'navigationContexts' => [
                'main',
            ],
            'excerpt' => [
                'icon' => [
                    'ids' => [$media->getId()],
                ],
            ],
        ]);

        self::createPage([
            'title' => 'Test 3',
            'url' => '/test-3',
            'navigationContexts' => [
                'footer',
            ],
        ]);

        self::createPage([
            'title' => 'Test 1A',
            'url' => '/test-1a',
            'article' => '<p>My Article 2</p>',
            'parent_path' => '/cmf/sulu_io/contents/test-1',
            'navigationContexts' => [
                'main',
            ],
        ]);

        self::createPage([
            'title' => 'Test 1B',
            'url' => '/test-1b',
            'article' => '<p>My Article 2</p>',
            'parent_path' => '/cmf/sulu_io/contents/test-1',
            'navigationContexts' => [
                'main',
            ],
        ]);

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
            [],
            'navigation__get.json',
        ];

        yield [
            [
                'context' => 'footer',
            ],
            'navigation__get_context_footer.json',
        ];

        yield [
            [
                'depth' => 2,
            ],
            'navigation__get_depth_2.json',
        ];

        yield [
            [
                'depth' => 2,
                'flat' => 'true',
            ],
            'navigation__get_depth_2_flat.json',
        ];

        yield [
            [
                'excerpt' => 'true',
            ],
            'navigation__get_excerpt.json',
        ];

        yield [
            [
                'uuid' => true,
            ],
            'navigation__get_uuid.json',
        ];
    }

    /**
     * @param mixed[] $filters
     *
     * @dataProvider provideAttributes
     */
    public function testGetAction(array $filters, string $expectedPatternFile): void
    {
        if ($filters['uuid'] ?? false) {
            $filters['uuid'] = self::$page1Uuid;
        }

        $context = 'main';
        if ($filters['context'] ?? false) {
            $context = $filters['context'];
        }

        $this->websiteClient->request('GET', '/api/navigations/' . $context . '?' . http_build_query($filters));

        $response = $this->websiteClient->getResponse();
        $this->assertInstanceOf(Response::class, $response);

        $this->assertResponseContent(
            $expectedPatternFile,
            $response,
            Response::HTTP_OK
        );
    }
}
