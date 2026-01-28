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

use CmsIg\Seal\EngineInterface;
use Sulu\Bundle\HeadlessBundle\Tests\Functional\BaseTestCase;
use Sulu\Bundle\HeadlessBundle\Tests\Traits\CreateCategoryTrait;
use Sulu\Bundle\HeadlessBundle\Tests\Traits\CreateMediaTrait;
use Sulu\Bundle\HeadlessBundle\Tests\Traits\CreatePageTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

class SearchControllerTest extends BaseTestCase
{
    use CreateCategoryTrait;
    use CreateMediaTrait;
    use CreatePageTrait;

    private KernelBrowser $websiteClient;

    private static ?int $category1Id = null;
    private static ?int $category2Id = null;

    public static function setUpBeforeClass(): void
    {
        static::purgeDatabase();
        self::bootKernel();

        $collection = self::createCollection('Test Collection', 'de');
        $media = self::createMedia('Test Image', $collection, 'de');
        self::getEntityManager()->flush();

        /** @var EngineInterface $engine */
        $engine = self::getContainer()->get(EngineInterface::class);

        // Drop and recreate schema to ensure fresh indexes
        $task = $engine->dropSchema(['return_slow_promise_result' => true]);
        $task->wait();
        $task = $engine->createSchema(['return_slow_promise_result' => true]);
        $task->wait();

        $entityManager = self::getEntityManager();
        $connection = $entityManager->getConnection();
        $connection->executeStatement('ALTER TABLE ca_categories AUTO_INCREMENT = 1');
        $connection->executeStatement('ALTER TABLE ta_tags AUTO_INCREMENT = 1');
        $entityManager->clear();

        $category1 = self::createCategory(['name' => 'Technology', 'key' => 'technology']);
        $category2 = self::createCategory(['name' => 'Development', 'key' => 'development']);
        $entityManager = self::getEntityManager();
        $entityManager->flush();
        self::$category1Id = $category1->getId();
        self::$category2Id = $category2->getId();

        $tagRepository = self::getContainer()->get('sulu.repository.tag');
        $tag1 = $tagRepository->createNew();
        $tag1->setName('cms');
        $entityManager->persist($tag1);
        $tag2 = $tagRepository->createNew();
        $tag2->setName('php');
        $entityManager->persist($tag2);
        $entityManager->flush();

        self::createPage(
            [
                'title' => 'Sulu is awesome',
                'url' => '/awesome-sulu',
                'excerpt' => [
                    'image' => [
                        'id' => $media->getId(),
                    ],
                ],
            ]
        );

        self::createPage(
            [
                'title' => 'SEAL is awesome',
                'url' => '/awesome-seal',
            ]
        );

        // Clear entity manager to ensure fresh state for routing
        self::getEntityManager()->clear();

        self::createPage([
            'title' => 'Content Management Systems',
            'url' => '/content-management',
            'excerpt' => [
                'title' => 'CMS Guide',
                'description' => 'Guide about content management',
                'categories' => [self::$category1Id, self::$category2Id],
            ],
        ]);

        self::createPage([
            'title' => 'Web Development Guide',
            'url' => '/web-development',
            'excerpt' => [
                'title' => 'Development Resources',
                'description' => 'Resources for developers',
                'tags' => [$tag1->getName(), $tag2->getName()],
            ],
        ]);

        self::createPage([
            'title' => 'PHP CMS Tutorial',
            'url' => '/php-cms-tutorial',
            'excerpt' => [
                'title' => 'Complete Tutorial',
                'description' => 'Learn to build a CMS',
                'categories' => [self::$category1Id],
                'tags' => [$tag1->getName()],
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
    public static function provideAttributes(): \Generator
    {
        yield [
            'SEAL',
            'website',
            'search__get_seal.json',
        ];

        yield [
            'awesome',
            'website',
            'search__get_awesome.json',
        ];

        yield 'page with categories' => [
            'CMS Guide',
            'website',
            'search__get_content_management.json',
        ];

        yield 'page with tags' => [
            'Development Resources',
            'website',
            'search__get_web_development.json',
        ];

        yield 'page with categories and tags' => [
            'Complete Tutorial',
            'website',
            'search__get_php_cms.json',
        ];
    }

    /**
     * @dataProvider provideAttributes
     */
    public function testGetAction(string $query, string $index, string $expectedPatternFile): void
    {
        $this->websiteClient->request('GET', '/api/search?q=' . $query . '&index=' . $index);

        $response = $this->websiteClient->getResponse();
        $this->assertInstanceOf(Response::class, $response);

        $this->assertResponseContent(
            $expectedPatternFile,
            $response,
            Response::HTTP_OK
        );
    }
}
