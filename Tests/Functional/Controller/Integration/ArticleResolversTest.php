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

namespace Sulu\Bundle\HeadlessBundle\Tests\Functional\Controller\Integration;

use Sulu\Article\Domain\Model\Article;
use Sulu\Bundle\HeadlessBundle\Tests\Functional\BaseTestCase;
use Sulu\Bundle\HeadlessBundle\Tests\Traits\CreateArticleTrait;
use Sulu\Bundle\HeadlessBundle\Tests\Traits\CreatePageTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

class ArticleResolversTest extends BaseTestCase
{
    use CreateArticleTrait;
    use CreatePageTrait;

    private KernelBrowser $websiteClient;

    private static Article $article1;
    private static Article $article2;

    public static function setUpBeforeClass(): void
    {
        static::purgeDatabase();
        self::bootKernel();

        self::$article1 = self::createArticle([
            'title' => 'Target Article One',
            'url' => '/articles/target-article-one',
            'template' => 'default',
        ]);

        self::$article2 = self::createArticle([
            'title' => 'Target Article Two',
            'url' => '/articles/target-article-two',
            'template' => 'default',
        ]);

        self::createPage([
            'title' => 'Article Multiple',
            'url' => '/article-multiple',
            'template' => 'resolver-test',
            'article_selection' => [self::$article1->getUuid(), self::$article2->getUuid()],
        ]);

        self::createPage([
            'title' => 'Article Empty',
            'url' => '/article-empty',
            'template' => 'resolver-test',
            'article_selection' => [],
        ]);

        self::createPage([
            'title' => 'Single Article Basic',
            'url' => '/single-article-basic',
            'template' => 'resolver-test',
            'single_article_selection' => self::$article1->getUuid(),
        ]);

        self::createPage([
            'title' => 'Single Article Null',
            'url' => '/single-article-null',
            'template' => 'resolver-test',
            'single_article_selection' => null,
        ]);

        self::getEntityManager()->clear();

        static::ensureKernelShutdown();
    }

    protected function setUp(): void
    {
        $this->websiteClient = $this->createWebsiteClient();
    }

    /**
     * @dataProvider articleSelectionProvider
     */
    public function testArticleSelection(string $url, string $fixture): void
    {
        $this->websiteClient->request('GET', $url . '.json');

        $response = $this->websiteClient->getResponse();

        $this->assertResponseContent(
            $fixture,
            $response,
            Response::HTTP_OK
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function articleSelectionProvider(): iterable
    {
        yield 'multiple' => ['/article-multiple', 'selection/article_selection__multiple.json'];
        yield 'empty' => ['/article-empty', 'selection/article_selection__empty.json'];
    }

    /**
     * @dataProvider singleArticleSelectionProvider
     */
    public function testSingleArticleSelection(string $url, string $fixture): void
    {
        $this->websiteClient->request('GET', $url . '.json');

        $response = $this->websiteClient->getResponse();

        $this->assertResponseContent(
            $fixture,
            $response,
            Response::HTTP_OK
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function singleArticleSelectionProvider(): iterable
    {
        yield 'basic' => ['/single-article-basic', 'selection/single_article_selection__basic.json'];
        yield 'null' => ['/single-article-null', 'selection/single_article_selection__null.json'];
    }
}
