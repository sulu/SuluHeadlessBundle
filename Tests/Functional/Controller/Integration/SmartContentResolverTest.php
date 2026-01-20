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

use Sulu\Bundle\HeadlessBundle\Tests\Functional\BaseTestCase;
use Sulu\Bundle\HeadlessBundle\Tests\Traits\CreateAccountTrait;
use Sulu\Bundle\HeadlessBundle\Tests\Traits\CreateContactTrait;
use Sulu\Bundle\HeadlessBundle\Tests\Traits\CreateMediaTrait;
use Sulu\Bundle\HeadlessBundle\Tests\Traits\CreatePageTrait;
use Sulu\Bundle\HeadlessBundle\Tests\Traits\CreateSnippetTrait;
use Sulu\Bundle\MediaBundle\Entity\CollectionInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

class SmartContentResolverTest extends BaseTestCase
{
    use CreateAccountTrait;
    use CreateContactTrait;
    use CreateMediaTrait;
    use CreatePageTrait;
    use CreateSnippetTrait;

    private KernelBrowser $websiteClient;

    private static CollectionInterface $collection;

    public static function setUpBeforeClass(): void
    {
        static::purgeDatabase();
        self::bootKernel();

        self::createSnippet([
            'title' => 'Smart Snippet One',
            'template' => 'default',
        ]);

        self::createSnippet([
            'title' => 'Smart Snippet Two',
            'template' => 'default',
        ]);

        self::$collection = self::createCollection('Smart Content Collection', 'de');
        self::createMedia('Smart Media One', self::$collection, 'de');
        self::createMedia('Smart Media Two', self::$collection, 'de');
        self::getEntityManager()->flush();

        self::createContact(['firstName' => 'Smart', 'lastName' => 'Contact One']);
        self::createContact(['firstName' => 'Smart', 'lastName' => 'Contact Two']);
        self::getEntityManager()->flush();

        self::createAccount(['name' => 'Smart Account One']);
        self::createAccount(['name' => 'Smart Account Two']);
        self::getEntityManager()->flush();

        $pagesParent = self::createPage([
            'title' => 'Pages Parent',
            'url' => '/pages-parent',
            'template' => 'default',
        ]);

        self::createPage([
            'title' => 'Content Page One',
            'url' => '/pages-parent/content-page-one',
            'template' => 'default',
            'parentId' => $pagesParent->getUuid(),
        ]);

        self::createPage([
            'title' => 'Content Page Two',
            'url' => '/pages-parent/content-page-two',
            'template' => 'default',
            'parentId' => $pagesParent->getUuid(),
        ]);

        $excerptParent = self::createPage([
            'title' => 'Excerpt Parent',
            'url' => '/excerpt-parent',
            'template' => 'default',
        ]);

        self::createPage([
            'title' => 'Page With Excerpt One',
            'url' => '/excerpt-parent/page-one',
            'template' => 'default',
            'parentId' => $excerptParent->getUuid(),
            'excerpt' => [
                'title' => 'Excerpt Title One',
                'description' => 'First page excerpt description',
            ],
        ]);

        self::createPage([
            'title' => 'Page With Excerpt Two',
            'url' => '/excerpt-parent/page-two',
            'template' => 'default',
            'parentId' => $excerptParent->getUuid(),
            'excerpt' => [
                'title' => 'Excerpt Title Two',
                'description' => 'Second page excerpt description',
            ],
        ]);

        self::createPage([
            'title' => 'Smart Content Pages',
            'url' => '/smart-content-pages',
            'template' => 'smart-content-providers',
            'pages_content' => [
                'dataSource' => $pagesParent->getUuid(),
            ],
        ]);

        self::createPage([
            'title' => 'Smart Content Pages With Excerpt',
            'url' => '/smart-content-pages-excerpt',
            'template' => 'smart-content-providers',
            'pages_with_excerpt' => [
                'dataSource' => $excerptParent->getUuid(),
            ],
        ]);

        self::createPage([
            'title' => 'Smart Content Pages Empty',
            'url' => '/smart-content-pages-empty',
            'template' => 'smart-content-providers',
        ]);

        self::createPage([
            'title' => 'Smart Content Snippets',
            'url' => '/smart-content-snippets',
            'template' => 'smart-content-providers',
        ]);

        self::createPage([
            'title' => 'Smart Content Media',
            'url' => '/smart-content-media',
            'template' => 'smart-content-providers',
            'media_content' => [
                'dataSource' => (string) self::$collection->getId(),
            ],
        ]);

        self::createPage([
            'title' => 'Smart Content Contacts',
            'url' => '/smart-content-contacts',
            'template' => 'smart-content-providers',
        ]);

        self::createPage([
            'title' => 'Smart Content Accounts',
            'url' => '/smart-content-accounts',
            'template' => 'smart-content-providers',
        ]);

        self::getEntityManager()->clear();

        static::ensureKernelShutdown();
    }

    protected function setUp(): void
    {
        $this->websiteClient = $this->createWebsiteClient();
    }

    /**
     * @dataProvider pagesProviderProvider
     */
    public function testPagesProvider(string $url, string $fixture): void
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
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function pagesProviderProvider(): iterable
    {
        yield 'pages content' => [
            '/smart-content-pages',
            'smart-content/pages__basic.json',
        ];

        yield 'pages empty (only test page itself)' => [
            '/smart-content-pages-empty',
            'smart-content/pages__empty.json',
        ];

        yield 'pages with excerpt properties' => [
            '/smart-content-pages-excerpt',
            'smart-content/pages__with_excerpt.json',
        ];
    }

    /**
     * @dataProvider snippetsProviderProvider
     */
    public function testSnippetsProvider(string $url, string $fixture): void
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
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function snippetsProviderProvider(): iterable
    {
        yield 'snippets content' => [
            '/smart-content-snippets',
            'smart-content/snippets__basic.json',
        ];
    }

    /**
     * @dataProvider mediaProviderProvider
     */
    public function testMediaProvider(string $url, string $fixture): void
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
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function mediaProviderProvider(): iterable
    {
        yield 'media content' => [
            '/smart-content-media',
            'smart-content/media__basic.json',
        ];
    }

    /**
     * @dataProvider contactsProviderProvider
     */
    public function testContactsProvider(string $url, string $fixture): void
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
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function contactsProviderProvider(): iterable
    {
        yield 'contacts content' => [
            '/smart-content-contacts',
            'smart-content/contacts__basic.json',
        ];
    }

    /**
     * @dataProvider accountsProviderProvider
     */
    public function testAccountsProvider(string $url, string $fixture): void
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
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function accountsProviderProvider(): iterable
    {
        yield 'accounts content' => [
            '/smart-content-accounts',
            'smart-content/accounts__basic.json',
        ];
    }
}
