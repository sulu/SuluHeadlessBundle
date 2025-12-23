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

use Sulu\Bundle\CategoryBundle\Entity\CategoryInterface;
use Sulu\Bundle\ContactBundle\Entity\AccountInterface;
use Sulu\Bundle\ContactBundle\Entity\ContactInterface;
use Sulu\Bundle\HeadlessBundle\Tests\Functional\BaseTestCase;
use Sulu\Bundle\HeadlessBundle\Tests\Traits\CreateAccountTrait;
use Sulu\Bundle\HeadlessBundle\Tests\Traits\CreateCategoryTrait;
use Sulu\Bundle\HeadlessBundle\Tests\Traits\CreateContactTrait;
use Sulu\Bundle\HeadlessBundle\Tests\Traits\CreatePageTrait;
use Sulu\Bundle\HeadlessBundle\Tests\Traits\CreateSnippetTrait;
use Sulu\Bundle\PageBundle\Document\PageDocument;
use Sulu\Bundle\SnippetBundle\Document\SnippetDocument;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

class SelectionResolversTest extends BaseTestCase
{
    use CreateAccountTrait;
    use CreateCategoryTrait;
    use CreateContactTrait;
    use CreatePageTrait;
    use CreateSnippetTrait;

    private KernelBrowser $websiteClient;

    private static CategoryInterface $category1;
    private static CategoryInterface $category2;
    private static ContactInterface $contact1;
    private static ContactInterface $contact2;
    private static AccountInterface $account1;
    private static AccountInterface $account2;
    private static PageDocument $targetPage1;
    private static PageDocument $targetPage2;
    private static SnippetDocument $snippet1;
    private static SnippetDocument $snippet2;

    public static function setUpBeforeClass(): void
    {
        self::initPhpcr();

        self::$category1 = self::createCategory(['name' => 'Category One']);
        self::$category2 = self::createCategory(['name' => 'Category Two']);
        self::getEntityManager()->flush();

        self::$contact1 = self::createContact(['firstName' => 'John', 'lastName' => 'Doe']);
        self::$contact2 = self::createContact(['firstName' => 'Jane', 'lastName' => 'Smith']);
        self::getEntityManager()->flush();

        self::$account1 = self::createAccount(['name' => 'Acme Corp']);
        self::$account2 = self::createAccount(['name' => 'Tech Inc']);
        self::getEntityManager()->flush();

        self::$targetPage1 = self::createPage([
            'title' => 'Target Page One',
            'url' => '/target-page-one',
            'template' => 'default',
        ]);

        self::$targetPage2 = self::createPage([
            'title' => 'Target Page Two',
            'url' => '/target-page-two',
            'template' => 'default',
        ]);

        self::$snippet1 = self::createSnippet([
            'title' => 'Snippet One',
            'template' => 'default',
        ]);

        self::$snippet2 = self::createSnippet([
            'title' => 'Snippet Two',
            'template' => 'default',
        ]);

        self::createPage([
            'title' => 'Category Multiple',
            'url' => '/category-multiple',
            'template' => 'resolver-test',
            'category_selection' => [self::$category1->getId(), self::$category2->getId()],
        ]);

        self::createPage([
            'title' => 'Category Empty',
            'url' => '/category-empty',
            'template' => 'resolver-test',
            'category_selection' => [],
        ]);

        self::createPage([
            'title' => 'Single Category Basic',
            'url' => '/single-category-basic',
            'template' => 'resolver-test',
            'single_category_selection' => self::$category1->getId(),
        ]);

        self::createPage([
            'title' => 'Single Category Null',
            'url' => '/single-category-null',
            'template' => 'resolver-test',
            'single_category_selection' => null,
        ]);

        self::createPage([
            'title' => 'Contact Multiple',
            'url' => '/contact-multiple',
            'template' => 'resolver-test',
            'contact_selection' => [self::$contact1->getId(), self::$contact2->getId()],
        ]);

        self::createPage([
            'title' => 'Contact Empty',
            'url' => '/contact-empty',
            'template' => 'resolver-test',
            'contact_selection' => [],
        ]);

        self::createPage([
            'title' => 'Single Contact Basic',
            'url' => '/single-contact-basic',
            'template' => 'resolver-test',
            'single_contact_selection' => self::$contact1->getId(),
        ]);

        self::createPage([
            'title' => 'Single Contact Null',
            'url' => '/single-contact-null',
            'template' => 'resolver-test',
            'single_contact_selection' => null,
        ]);

        self::createPage([
            'title' => 'Account Multiple',
            'url' => '/account-multiple',
            'template' => 'resolver-test',
            'account_selection' => [self::$account1->getId(), self::$account2->getId()],
        ]);

        self::createPage([
            'title' => 'Account Empty',
            'url' => '/account-empty',
            'template' => 'resolver-test',
            'account_selection' => [],
        ]);

        self::createPage([
            'title' => 'Single Account Basic',
            'url' => '/single-account-basic',
            'template' => 'resolver-test',
            'single_account_selection' => self::$account1->getId(),
        ]);

        self::createPage([
            'title' => 'Single Account Null',
            'url' => '/single-account-null',
            'template' => 'resolver-test',
            'single_account_selection' => null,
        ]);

        self::createPage([
            'title' => 'Contact Account Mixed',
            'url' => '/contact-account-mixed',
            'template' => 'resolver-test',
            'contact_account_selection' => [
                'c' . self::$contact1->getId(),
                'a' . self::$account1->getId(),
            ],
        ]);

        self::createPage([
            'title' => 'Contact Account Empty',
            'url' => '/contact-account-empty',
            'template' => 'resolver-test',
            'contact_account_selection' => [],
        ]);

        self::createPage([
            'title' => 'Page Multiple',
            'url' => '/page-multiple',
            'template' => 'resolver-test',
            'page_selection' => [self::$targetPage1->getUuid(), self::$targetPage2->getUuid()],
        ]);

        self::createPage([
            'title' => 'Page Empty',
            'url' => '/page-empty',
            'template' => 'resolver-test',
            'page_selection' => [],
        ]);

        self::createPage([
            'title' => 'Single Page Basic',
            'url' => '/single-page-basic',
            'template' => 'resolver-test',
            'single_page_selection' => self::$targetPage1->getUuid(),
        ]);

        self::createPage([
            'title' => 'Single Page Null',
            'url' => '/single-page-null',
            'template' => 'resolver-test',
            'single_page_selection' => null,
        ]);

        self::createPage([
            'title' => 'Snippet Multiple',
            'url' => '/snippet-multiple',
            'template' => 'resolver-test',
            'snippet_selection' => [self::$snippet1->getUuid(), self::$snippet2->getUuid()],
        ]);

        self::createPage([
            'title' => 'Snippet Empty',
            'url' => '/snippet-empty',
            'template' => 'resolver-test',
            'snippet_selection' => [],
        ]);

        self::createPage([
            'title' => 'Single Snippet Basic',
            'url' => '/single-snippet-basic',
            'template' => 'resolver-test',
            'single_snippet_selection' => self::$snippet1->getUuid(),
        ]);

        self::createPage([
            'title' => 'Single Snippet Null',
            'url' => '/single-snippet-null',
            'template' => 'resolver-test',
            'single_snippet_selection' => null,
        ]);

        static::ensureKernelShutdown();
    }

    protected function setUp(): void
    {
        $this->websiteClient = $this->createWebsiteClient();
    }

    /**
     * @dataProvider categorySelectionProvider
     */
    public function testCategorySelection(string $url, string $fixture): void
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
    public static function categorySelectionProvider(): iterable
    {
        yield 'multiple' => ['/category-multiple', 'selection/category_selection__multiple.json'];
        yield 'empty' => ['/category-empty', 'selection/category_selection__empty.json'];
    }

    /**
     * @dataProvider singleCategorySelectionProvider
     */
    public function testSingleCategorySelection(string $url, string $fixture): void
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
    public static function singleCategorySelectionProvider(): iterable
    {
        yield 'basic' => ['/single-category-basic', 'selection/single_category_selection__basic.json'];
        yield 'null' => ['/single-category-null', 'selection/single_category_selection__null.json'];
    }

    /**
     * @dataProvider contactSelectionProvider
     */
    public function testContactSelection(string $url, string $fixture): void
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
    public static function contactSelectionProvider(): iterable
    {
        yield 'multiple' => ['/contact-multiple', 'selection/contact_selection__multiple.json'];
        yield 'empty' => ['/contact-empty', 'selection/contact_selection__empty.json'];
    }

    /**
     * @dataProvider singleContactSelectionProvider
     */
    public function testSingleContactSelection(string $url, string $fixture): void
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
    public static function singleContactSelectionProvider(): iterable
    {
        yield 'basic' => ['/single-contact-basic', 'selection/single_contact_selection__basic.json'];
        yield 'null' => ['/single-contact-null', 'selection/single_contact_selection__null.json'];
    }

    /**
     * @dataProvider accountSelectionProvider
     */
    public function testAccountSelection(string $url, string $fixture): void
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
    public static function accountSelectionProvider(): iterable
    {
        yield 'multiple' => ['/account-multiple', 'selection/account_selection__multiple.json'];
        yield 'empty' => ['/account-empty', 'selection/account_selection__empty.json'];
    }

    /**
     * @dataProvider singleAccountSelectionProvider
     */
    public function testSingleAccountSelection(string $url, string $fixture): void
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
    public static function singleAccountSelectionProvider(): iterable
    {
        yield 'basic' => ['/single-account-basic', 'selection/single_account_selection__basic.json'];
        yield 'null' => ['/single-account-null', 'selection/single_account_selection__null.json'];
    }

    /**
     * @dataProvider contactAccountSelectionProvider
     */
    public function testContactAccountSelection(string $url, string $fixture): void
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
    public static function contactAccountSelectionProvider(): iterable
    {
        yield 'mixed' => ['/contact-account-mixed', 'selection/contact_account_selection__mixed.json'];
        yield 'empty' => ['/contact-account-empty', 'selection/contact_account_selection__empty.json'];
    }

    /**
     * @dataProvider pageSelectionProvider
     */
    public function testPageSelection(string $url, string $fixture): void
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
    public static function pageSelectionProvider(): iterable
    {
        yield 'multiple' => ['/page-multiple', 'selection/page_selection__multiple.json'];
        yield 'empty' => ['/page-empty', 'selection/page_selection__empty.json'];
    }

    /**
     * @dataProvider singlePageSelectionProvider
     */
    public function testSinglePageSelection(string $url, string $fixture): void
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
    public static function singlePageSelectionProvider(): iterable
    {
        yield 'basic' => ['/single-page-basic', 'selection/single_page_selection__basic.json'];
        yield 'null' => ['/single-page-null', 'selection/single_page_selection__null.json'];
    }

    /**
     * @dataProvider snippetSelectionProvider
     */
    public function testSnippetSelection(string $url, string $fixture): void
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
    public static function snippetSelectionProvider(): iterable
    {
        yield 'multiple' => ['/snippet-multiple', 'selection/snippet_selection__multiple.json'];
        yield 'empty' => ['/snippet-empty', 'selection/snippet_selection__empty.json'];
    }

    /**
     * @dataProvider singleSnippetSelectionProvider
     */
    public function testSingleSnippetSelection(string $url, string $fixture): void
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
    public static function singleSnippetSelectionProvider(): iterable
    {
        yield 'basic' => ['/single-snippet-basic', 'selection/single_snippet_selection__basic.json'];
        yield 'null' => ['/single-snippet-null', 'selection/single_snippet_selection__null.json'];
    }
}
