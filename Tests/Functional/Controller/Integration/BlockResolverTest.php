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
use Sulu\Bundle\HeadlessBundle\Tests\Traits\CreateMediaTrait;
use Sulu\Bundle\HeadlessBundle\Tests\Traits\CreatePageTrait;
use Sulu\Bundle\MediaBundle\DataFixtures\ORM\LoadCollectionTypes;
use Sulu\Bundle\MediaBundle\DataFixtures\ORM\LoadMediaTypes;
use Sulu\Bundle\MediaBundle\Entity\CollectionInterface;
use Sulu\Bundle\MediaBundle\Entity\MediaInterface;
use Sulu\Bundle\PageBundle\Document\PageDocument;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

class BlockResolverTest extends BaseTestCase
{
    use CreateMediaTrait;
    use CreatePageTrait;

    private KernelBrowser $websiteClient;

    private static CollectionInterface $collection;
    private static MediaInterface $media;
    private static PageDocument $targetPage;

    public static function setUpBeforeClass(): void
    {
        self::initPhpcr();

        $collectionTypeFixture = new LoadCollectionTypes();
        $collectionTypeFixture->load(self::getEntityManager());
        $mediaTypeFixture = new LoadMediaTypes();
        $mediaTypeFixture->load(self::getEntityManager());

        self::$collection = self::createCollection('Test Collection', 'de');
        self::$media = self::createMedia('Test Image 1', self::$collection, 'de');
        self::getEntityManager()->flush();

        self::$targetPage = self::createPage([
            'title' => 'Target Page',
            'url' => '/target-page',
            'template' => 'default',
        ]);

        self::createPage([
            'title' => 'Text Editor Basic',
            'url' => '/text-editor-basic',
            'template' => 'resolver-test',
            'text_editor' => '<p>Hello World</p>',
        ]);

        self::createPage([
            'title' => 'Text Editor Markup',
            'url' => '/text-editor-markup',
            'template' => 'resolver-test',
            'text_editor' => '<h1>Heading</h1><p>Paragraph with <strong>bold</strong> and <em>italic</em> text.</p><ul><li>Item 1</li><li>Item 2</li></ul>',
        ]);

        self::createPage([
            'title' => 'Text Editor Empty',
            'url' => '/text-editor-empty',
            'template' => 'resolver-test',
            'text_editor' => '',
        ]);

        self::createPage([
            'title' => 'Link External',
            'url' => '/link-external',
            'template' => 'resolver-test',
            'link' => [
                'provider' => 'external',
                'href' => 'https://example.com',
                'title' => 'Example Site',
                'target' => '_blank',
                'locale' => 'de',
            ],
        ]);

        self::createPage([
            'title' => 'Link Internal',
            'url' => '/link-internal',
            'template' => 'resolver-test',
            'link' => [
                'provider' => 'page',
                'href' => self::$targetPage->getUuid(),
                'title' => 'Internal Link Title',
                'target' => '_self',
                'locale' => 'de',
            ],
        ]);

        self::createPage([
            'title' => 'Link Anchor',
            'url' => '/link-anchor',
            'template' => 'resolver-test',
            'link' => [
                'provider' => 'external',
                'href' => 'https://example.com',
                'title' => 'Example with anchor',
                'target' => '_blank',
                'anchor' => 'section1',
                'locale' => 'de',
            ],
        ]);

        self::createPage([
            'title' => 'Link Null',
            'url' => '/link-null',
            'template' => 'resolver-test',
            'link' => null,
        ]);

        self::createPage([
            'title' => 'Block Text',
            'url' => '/block-text',
            'template' => 'resolver-test',
            'blocks' => [
                [
                    'type' => 'text',
                    'title' => 'Block Title',
                    'content' => '<p>Block content here</p>',
                ],
            ],
        ]);

        self::createPage([
            'title' => 'Block Media',
            'url' => '/block-media',
            'template' => 'resolver-test',
            'blocks' => [
                [
                    'type' => 'media',
                    'images' => ['ids' => [self::$media->getId()]],
                    'caption' => 'Image caption',
                ],
            ],
        ]);

        self::createPage([
            'title' => 'Block Multiple',
            'url' => '/block-multiple',
            'template' => 'resolver-test',
            'blocks' => [
                [
                    'type' => 'text',
                    'title' => 'First Block',
                    'content' => '<p>First block content</p>',
                ],
                [
                    'type' => 'media',
                    'images' => ['ids' => [self::$media->getId()]],
                    'caption' => 'Media block caption',
                ],
                [
                    'type' => 'text',
                    'title' => 'Third Block',
                    'content' => '<p>Third block content</p>',
                ],
            ],
        ]);

        self::createPage([
            'title' => 'Block Nested',
            'url' => '/block-nested',
            'template' => 'resolver-test',
            'blocks' => [
                [
                    'type' => 'nested',
                    'inner_blocks' => [
                        [
                            'type' => 'inner_text',
                            'text' => '<p>Inner block content</p>',
                        ],
                    ],
                ],
            ],
        ]);

        self::createPage([
            'title' => 'Block Empty',
            'url' => '/block-empty',
            'template' => 'resolver-test',
            'blocks' => [],
        ]);

        static::ensureKernelShutdown();
    }

    protected function setUp(): void
    {
        $this->websiteClient = $this->createWebsiteClient();
    }

    /**
     * @dataProvider textEditorProvider
     */
    public function testTextEditor(string $url, string $fixture): void
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
    public static function textEditorProvider(): iterable
    {
        yield 'basic' => ['/text-editor-basic', 'block/text_editor__basic.json'];
        yield 'with markup' => ['/text-editor-markup', 'block/text_editor__markup.json'];
        yield 'empty' => ['/text-editor-empty', 'block/text_editor__empty.json'];
    }

    /**
     * @dataProvider linkProvider
     */
    public function testLink(string $url, string $fixture): void
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
    public static function linkProvider(): iterable
    {
        yield 'external' => ['/link-external', 'block/link__external.json'];
        yield 'internal' => ['/link-internal', 'block/link__internal.json'];
        yield 'with anchor' => ['/link-anchor', 'block/link__anchor.json'];
        yield 'null' => ['/link-null', 'block/link__null.json'];
    }

    /**
     * @dataProvider blockProvider
     */
    public function testBlock(string $url, string $fixture): void
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
    public static function blockProvider(): iterable
    {
        yield 'text type' => ['/block-text', 'block/block__text.json'];
        yield 'media type' => ['/block-media', 'block/block__media.json'];
        yield 'multiple types' => ['/block-multiple', 'block/block__multiple.json'];
        yield 'nested' => ['/block-nested', 'block/block__nested.json'];
        yield 'empty' => ['/block-empty', 'block/block__empty.json'];
    }
}
