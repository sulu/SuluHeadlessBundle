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

use Ramsey\Uuid\Uuid;
use Sulu\Bundle\PageBundle\Document\PageDocument;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Component\DocumentManager\DocumentManagerInterface;
use Sulu\Component\PHPCR\SessionManager\SessionManagerInterface;
use Symfony\Component\HttpFoundation\Response;

class HeadlessWebsiteControllerTest extends SuluTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->initPhpcr();
    }

    public function testIndexAction(): void
    {
        $page = $this->createPage('Test', '/test');

        $websiteClient = $this->createWebsiteClient();
        $websiteClient->request('GET', $page->getResourceSegment() . '.json');

        $response = $websiteClient->getResponse();
        $this->assertInstanceOf(Response::class, $response);
        $result = json_decode((string) $response->getContent(), true);
        $this->assertSame(200, $response->getStatusCode());

        /** @var \DateTimeImmutable $authored */
        $authored = $page->getAuthored();

        $this->assertSame(
            [
                'id' => $page->getUuid(),
                'type' => 'page',
                'template' => $page->getStructureType(),
                'content' => [
                    'title' => 'Test',
                    'url' => '/test',
                    'article' => '',
                ],
                'view' => [
                    'title' => [],
                    'url' => [],
                    'article' => [],
                ],
                'extension' => [],
                'authored' => $authored->format(\DateTimeImmutable::ISO8601),
                'changed' => $page->getChanged()->format(\DateTimeImmutable::ISO8601),
                'created' => $page->getCreated()->format(\DateTimeImmutable::ISO8601),
            ],
            $result
        );
    }

    private function createPage(string $title, string $resourceSegment, string $locale = 'de'): PageDocument
    {
        /** @var DocumentManagerInterface $documentManager */
        $documentManager = $this->getContainer()->get('sulu_document_manager.document_manager');

        /** @var SessionManagerInterface $sessionManager */
        $sessionManager = $this->getContainer()->get('sulu.phpcr.session');

        /** @var PageDocument $page */
        $page = $documentManager->create('page');

        $uuidReflection = new \ReflectionProperty(PageDocument::class, 'uuid');
        $uuidReflection->setAccessible(true);
        $uuidReflection->setValue($page, Uuid::uuid4()->toString());

        $page->setTitle($title);
        $page->setStructureType('default');
        $page->setParent($documentManager->find($sessionManager->getContentPath('sulu_io')));
        $page->setResourceSegment($resourceSegment);

        $documentManager->persist($page, $locale);
        $documentManager->publish($page, $locale);
        $documentManager->flush();

        return $page;
    }
}
