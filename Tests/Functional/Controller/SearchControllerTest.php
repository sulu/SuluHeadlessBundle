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
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

class SearchControllerTest extends BaseTestCase
{
    use CreatePageTrait;

    /**
     * @var KernelBrowser
     */
    private $websiteClient;

    public static function setUpBeforeClass(): void
    {
        self::initPhpcr();

        $searchManager = self::getContainer()->get('massive_search.search_manager');
        foreach ($searchManager->getIndexNames() as $indexName) {
            $searchManager->purge($indexName);
        }
        $searchManager->flush();

        self::createPage(
            [
                'title' => 'Sulu is awesome',
                'url' => '/awesome-sulu',
            ]
        )->getUuid();

        self::createPage(
            [
                'title' => 'MASSIVE ART is awesome',
                'url' => '/awesome-massive-art',
            ]
        );

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
            'massive',
            ['page_sulu_io_published'],
            'search__get_massive.json',
        ];

        yield [
            'awesome',
            ['page_sulu_io_published'],
            'search__get_awesome.json',
        ];
    }

    /**
     * @param string[] $indices
     *
     * @dataProvider provideAttributes
     */
    public function testGetAction(string $query, array $indices, string $expectedPatternFile): void
    {
        $this->websiteClient->request('GET', '/api/search?q=' . $query . '&indices=' . implode(',', $indices));

        $response = $this->websiteClient->getResponse();
        $this->assertInstanceOf(Response::class, $response);

        $this->assertResponseContent(
            $expectedPatternFile,
            $response,
            Response::HTTP_OK
        );
    }
}
