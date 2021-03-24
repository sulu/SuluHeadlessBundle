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

namespace Sulu\Bundle\HeadlessBundle\Tests\Unit\Content;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\DocumentManagerBundle\Bridge\DocumentInspector;
use Sulu\Bundle\HeadlessBundle\Content\ContentResolverInterface;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\StructureResolver;
use Sulu\Bundle\PageBundle\Document\HomeDocument;
use Sulu\Bundle\PageBundle\Document\PageDocument;
use Sulu\Bundle\SnippetBundle\Document\SnippetDocument;
use Sulu\Bundle\WebsiteBundle\ReferenceStore\ReferenceStoreInterface;
use Sulu\Bundle\WebsiteBundle\ReferenceStore\ReferenceStorePoolInterface;
use Sulu\Component\Content\Compat\PropertyInterface;
use Sulu\Component\Content\Compat\Structure\StructureBridge;
use Sulu\Component\Content\Compat\StructureManagerInterface;
use Sulu\Component\DocumentManager\Metadata;

class StructureResolverTest extends TestCase
{
    /**
     * @var StructureBridge|ObjectProphecy
     */
    private $excerpt;

    /**
     * @var ContentResolverInterface|ObjectProphecy
     */
    private $contentResolver;

    /**
     * @var StructureManagerInterface|ObjectProphecy
     */
    private $structureManager;

    /**
     * @var DocumentInspector|ObjectProphecy
     */
    private $documentInspector;

    /**
     * @var ReferenceStorePoolInterface|ObjectProphecy
     */
    private $referenceStorePool;

    /**
     * @var StructureResolver
     */
    private $structureResolver;

    protected function setUp(): void
    {
        $this->contentResolver = $this->prophesize(ContentResolverInterface::class);
        $this->structureManager = $this->prophesize(StructureManagerInterface::class);
        $this->documentInspector = $this->prophesize(DocumentInspector::class);

        $this->excerpt = $this->prophesizeExcerpt();
        $this->structureManager->getStructure('excerpt')->willReturn($this->excerpt->reveal());

        $this->referenceStorePool = $this->prophesize(ReferenceStorePoolInterface::class);

        $this->structureResolver = new StructureResolver(
            $this->contentResolver->reveal(),
            $this->structureManager->reveal(),
            $this->documentInspector->reveal(),
            $this->referenceStorePool->reveal()
        );
    }

    /**
     * @return ObjectProphecy<StructureBridge>
     */
    private function prophesizeExcerpt(): ObjectProphecy
    {
        $excerpt = $this->prophesize(StructureBridge::class);

        $titleProperty = $this->prophesize(PropertyInterface::class);
        $titleProperty->getName()->willReturn('title');
        $titleProperty->setValue(Argument::any())->willReturn();

        $excerpt->getProperties(true)->willReturn([$titleProperty->reveal()]);
        $excerpt->getProperty('title')->willReturn($titleProperty->reveal());

        $this->contentResolver->resolve(Argument::any(), $titleProperty->reveal(), Argument::cetera())->will(
            function ($arguments) {
                return new ContentView($arguments[0]);
            }
        );

        return $excerpt;
    }

    public function testResolvePage(): void
    {
        $structure = $this->prophesize(StructureBridge::class);
        $pageDocument = $this->prophesize(PageDocument::class);
        $pageMetadata = $this->prophesize(Metadata::class);

        // expected object calls
        $structure->getUuid()->willReturn('123-123-123')->shouldBeCalled();
        $structure->getWebspaceKey()->willReturn('sulu_io')->shouldBeCalled();

        $now = new \DateTimeImmutable();

        $pageDocument->getStructureType()->willReturn('default')->shouldBeCalled();
        $pageDocument->getAuthored()->willReturn($now)->shouldBeCalled();
        $pageDocument->getAuthor()->willReturn(1)->shouldBeCalled();
        $pageDocument->getCreated()->willReturn($now)->shouldBeCalled();
        $pageDocument->getCreator()->willReturn(2)->shouldBeCalled();
        $pageDocument->getChanged()->willReturn($now)->shouldBeCalled();
        $pageDocument->getChanger()->willReturn(3)->shouldBeCalled();
        $pageDocument->getExtensionsData()
            ->willReturn([
                'seo' => [
                    'title' => 'seo-title',
                    'noIndex' => false,
                ],
                'excerpt' => [
                    'title' => 'excerpt-title',
                    'categories' => [1, 2, 3],
                    'tags' => [1, 2, 3],
                    'icon' => [1, 2, 3],
                    'images' => [1, 2, 3],
                ],
            ])
            ->shouldBeCalled();

        $pageMetadata->getAlias()
            ->willReturn('page')
            ->shouldBeCalled();

        $structure->getDocument()
            ->willReturn($pageDocument->reveal())
            ->shouldBeCalled();

        $titleProperty = $this->prophesize(PropertyInterface::class);
        $titleProperty->getName()->willReturn('title');
        $titleProperty->getValue()->willReturn('test-123');
        $mediaProperty = $this->prophesize(PropertyInterface::class);
        $mediaProperty->getName()->willReturn('media');
        $mediaProperty->getValue()->willReturn(['ids' => [1, 2, 3]]);

        $structure->getProperties(true)->willReturn(
            [
                $titleProperty->reveal(),
                $mediaProperty->reveal(),
            ]
        );

        $titleContentView = $this->prophesize(ContentView::class);
        $titleContentView->getContent()->willReturn('test-123');
        $titleContentView->getView()->willReturn([]);

        $mediaContentView = $this->prophesize(ContentView::class);
        $mediaContentView->getContent()->willReturn(['media1', 'media2', 'media3']);
        $mediaContentView->getView()->willReturn(['ids' => [1, 2, 3]]);

        // expected service calls
        $this->documentInspector->getMetadata($pageDocument->reveal())
            ->willReturn($pageMetadata->reveal())
            ->shouldBeCalled();

        $this->contentResolver->resolve('test-123', $titleProperty->reveal(), 'en', ['webspaceKey' => 'sulu_io'])
            ->willReturn($titleContentView->reveal())
            ->shouldBeCalled();

        $this->contentResolver->resolve(
            ['ids' => [1, 2, 3]],
            $mediaProperty->reveal(),
            'en',
            ['webspaceKey' => 'sulu_io']
        )->willReturn($mediaContentView->reveal())
            ->shouldBeCalled();

        $referenceStore = $this->prophesize(ReferenceStoreInterface::class);
        $referenceStore->add('123-123-123')
            ->shouldBeCalled();

        $this->referenceStorePool->getStore('content')
            ->willReturn($referenceStore->reveal())
            ->shouldBeCalled();

        // call test function
        $result = $this->structureResolver->resolve($structure->reveal(), 'en');

        $this->assertSame(
            [
                'id' => '123-123-123',
                'type' => 'page',
                'template' => 'default',
                'content' => [
                    'title' => 'test-123',
                    'media' => ['media1', 'media2', 'media3'],
                ],
                'view' => [
                    'title' => [],
                    'media' => ['ids' => [1, 2, 3]],
                ],
                'author' => 1,
                'authored' => $now->format(\DateTimeImmutable::ISO8601),
                'changer' => 3,
                'changed' => $now->format(\DateTimeImmutable::ISO8601),
                'creator' => 2,
                'created' => $now->format(\DateTimeImmutable::ISO8601),
                'extension' => [
                    'seo' => [
                        'title' => 'seo-title',
                        'noIndex' => false,
                    ],
                    'excerpt' => [
                        'title' => 'excerpt-title',
                    ],
                ],
            ],
            $result
        );
    }

    public function testResolveHomepage(): void
    {
        $structure = $this->prophesize(StructureBridge::class);
        $homepageDocument = $this->prophesize(HomeDocument::class);
        $homepageMetadata = $this->prophesize(Metadata::class);

        // expected object calls
        $structure->getUuid()->willReturn('123-123-123')->shouldBeCalled();
        $structure->getWebspaceKey()->willReturn('sulu_io')->shouldBeCalled();

        $structure->getDocument()->willReturn($homepageDocument->reveal())->shouldBeCalled();

        $now = new \DateTimeImmutable();

        $homepageDocument->getStructureType()->willReturn('default')->shouldBeCalled();
        $homepageDocument->getAuthored()->willReturn($now)->shouldBeCalled();
        $homepageDocument->getAuthor()->willReturn(1)->shouldBeCalled();
        $homepageDocument->getCreated()->willReturn($now)->shouldBeCalled();
        $homepageDocument->getCreator()->willReturn(2)->shouldBeCalled();
        $homepageDocument->getChanged()->willReturn($now)->shouldBeCalled();
        $homepageDocument->getChanger()->willReturn(3)->shouldBeCalled();
        $homepageDocument->getExtensionsData()->willReturn([])->shouldBeCalled();

        $homepageMetadata->getAlias()->willReturn('home')->shouldBeCalled();

        $titleProperty = $this->prophesize(PropertyInterface::class);
        $titleProperty->getName()->willReturn('title')->shouldBeCalled();
        $titleProperty->getValue()->willReturn('test-123')->shouldBeCalled();

        $mediaProperty = $this->prophesize(PropertyInterface::class);
        $mediaProperty->getName()->willReturn('media')->shouldBeCalled();
        $mediaProperty->getValue()->willReturn(['ids' => [1, 2, 3]])->shouldBeCalled();

        $structure->getProperties(true)->willReturn(
            [
                $titleProperty->reveal(),
                $mediaProperty->reveal(),
            ]
        )->shouldBeCalled();

        $titleContentView = $this->prophesize(ContentView::class);
        $titleContentView->getContent()->willReturn('test-123')->shouldBeCalled();
        $titleContentView->getView()->willReturn([]);

        $mediaContentView = $this->prophesize(ContentView::class);
        $mediaContentView->getContent()->willReturn(['media1', 'media2', 'media3']);
        $mediaContentView->getView()->willReturn(['ids' => [1, 2, 3]]);

        // expected service calls
        $this->documentInspector->getMetadata($homepageDocument->reveal())
            ->willReturn($homepageMetadata->reveal())
            ->shouldBeCalled();

        $this->contentResolver->resolve('test-123', $titleProperty->reveal(), 'en', ['webspaceKey' => 'sulu_io'])
            ->willReturn($titleContentView->reveal())
            ->shouldBeCalled();

        $this->contentResolver->resolve(
            ['ids' => [1, 2, 3]],
            $mediaProperty->reveal(),
            'en',
            ['webspaceKey' => 'sulu_io']
        )->willReturn($mediaContentView->reveal())
            ->shouldBeCalled();

        $referenceStore = $this->prophesize(ReferenceStoreInterface::class);
        $referenceStore->add('123-123-123')
            ->shouldBeCalled();

        $this->referenceStorePool->getStore('content')
            ->willReturn($referenceStore->reveal())
            ->shouldBeCalled();

        // call test function
        $result = $this->structureResolver->resolve($structure->reveal(), 'en');

        $this->assertSame(
            [
                'id' => '123-123-123',
                'type' => 'page',
                'template' => 'default',
                'content' => [
                    'title' => 'test-123',
                    'media' => ['media1', 'media2', 'media3'],
                ],
                'view' => [
                    'title' => [],
                    'media' => ['ids' => [1, 2, 3]],
                ],
                'author' => 1,
                'authored' => $now->format(\DateTimeImmutable::ISO8601),
                'changer' => 3,
                'changed' => $now->format(\DateTimeImmutable::ISO8601),
                'creator' => 2,
                'created' => $now->format(\DateTimeImmutable::ISO8601),
                'extension' => [
                    'excerpt' => [
                        'title' => null,
                    ],
                ],
            ],
            $result
        );
    }

    public function testResolveSnippet(): void
    {
        $structure = $this->prophesize(StructureBridge::class);
        $snippetDocument = $this->prophesize(SnippetDocument::class);
        $snippetMetadata = $this->prophesize(Metadata::class);

        // expected object calls
        $structure->getUuid()->willReturn('123-123-123')->shouldBeCalled();
        $structure->getWebspaceKey()->willReturn('sulu_io')->shouldBeCalled();

        $structure->getDocument()->willReturn($snippetDocument->reveal())->shouldBeCalled();

        $now = new \DateTimeImmutable();

        $snippetDocument->getStructureType()->willReturn('default')->shouldBeCalled();
        $snippetDocument->getCreated()->willReturn($now)->shouldBeCalled();
        $snippetDocument->getCreator()->willReturn(2)->shouldBeCalled();
        $snippetDocument->getChanged()->willReturn($now)->shouldBeCalled();
        $snippetDocument->getChanger()->willReturn(3)->shouldBeCalled();
        $snippetDocument->getExtensionsData()->willReturn([])->shouldBeCalled();

        $snippetMetadata->getAlias()->willReturn('snippet')->shouldBeCalled();

        $titleProperty = $this->prophesize(PropertyInterface::class);
        $titleProperty->getName()->willReturn('title')->shouldBeCalled();
        $titleProperty->getValue()->willReturn('test-123')->shouldBeCalled();

        $mediaProperty = $this->prophesize(PropertyInterface::class);
        $mediaProperty->getName()->willReturn('media')->shouldBeCalled();
        $mediaProperty->getValue()->willReturn(['ids' => [1, 2, 3]])->shouldBeCalled();

        $structure->getProperties(true)->willReturn(
            [
                $titleProperty->reveal(),
                $mediaProperty->reveal(),
            ]
        )->shouldBeCalled();

        $titleContentView = $this->prophesize(ContentView::class);
        $titleContentView->getContent()->willReturn('test-123');
        $titleContentView->getView()->willReturn([]);

        $mediaContentView = $this->prophesize(ContentView::class);
        $mediaContentView->getContent()->willReturn(['media1', 'media2', 'media3']);
        $mediaContentView->getView()->willReturn(['ids' => [1, 2, 3]]);

        // expected service calls
        $this->documentInspector->getMetadata($snippetDocument->reveal())
            ->willReturn($snippetMetadata->reveal())
            ->shouldBeCalled();

        $this->contentResolver->resolve('test-123', $titleProperty->reveal(), 'en', ['webspaceKey' => 'sulu_io'])
            ->willReturn($titleContentView->reveal())
            ->shouldBeCalled();

        $this->contentResolver->resolve(
            ['ids' => [1, 2, 3]],
            $mediaProperty->reveal(),
            'en',
            ['webspaceKey' => 'sulu_io']
        )->willReturn($mediaContentView->reveal())
            ->shouldBeCalled();

        $referenceStore = $this->prophesize(ReferenceStoreInterface::class);
        $referenceStore->add('123-123-123')
            ->shouldBeCalled();

        $this->referenceStorePool->getStore('snippet')
            ->willReturn($referenceStore->reveal())
            ->shouldBeCalled();

        // call test function
        $result = $this->structureResolver->resolve($structure->reveal(), 'en');

        $this->assertSame(
            [
                'id' => '123-123-123',
                'type' => 'snippet',
                'template' => 'default',
                'content' => [
                    'title' => 'test-123',
                    'media' => ['media1', 'media2', 'media3'],
                ],
                'view' => [
                    'title' => [],
                    'media' => ['ids' => [1, 2, 3]],
                ],
                'author' => null,
                'authored' => null,
                'changer' => 3,
                'changed' => $now->format(\DateTimeImmutable::ISO8601),
                'creator' => 2,
                'created' => $now->format(\DateTimeImmutable::ISO8601),
                'extension' => [
                    'excerpt' => [
                        'title' => null,
                    ],
                ],
            ],
            $result
        );
    }

    public function testResolveProperties(): void
    {
        $structure = $this->prophesize(StructureBridge::class);
        $pageDocument = $this->prophesize(PageDocument::class);
        $pageMetadata = $this->prophesize(Metadata::class);

        // expected object calls
        $structure->getUuid()->willReturn('123-123-123')->shouldBeCalled();
        $structure->getWebspaceKey()->willReturn('sulu_io')->shouldBeCalled();

        $now = new \DateTimeImmutable();

        $pageDocument->getStructureType()->willReturn('default')->shouldBeCalled();
        $pageDocument->getAuthored()->willReturn($now)->shouldBeCalled();
        $pageDocument->getAuthor()->willReturn(1)->shouldBeCalled();
        $pageDocument->getCreated()->willReturn($now)->shouldBeCalled();
        $pageDocument->getCreator()->willReturn(2)->shouldBeCalled();
        $pageDocument->getChanged()->willReturn($now)->shouldBeCalled();
        $pageDocument->getChanger()->willReturn(3)->shouldBeCalled();
        $pageDocument->getExtensionsData()
            ->willReturn([
                'seo' => [
                    'title' => 'seo-title',
                    'description' => 'seo-description',
                    'noIndex' => false,
                ],
                'excerpt' => [
                    'title' => 'excerpt-title',
                    'categories' => [1, 2, 3],
                    'tags' => [1, 2, 3],
                    'icon' => [1, 2, 3],
                    'images' => [1, 2, 3],
                ],
            ])
            ->shouldBeCalled();

        $pageMetadata->getAlias()
            ->willReturn('page')
            ->shouldBeCalled();

        $structure->getDocument()
            ->willReturn($pageDocument->reveal())
            ->shouldBeCalled();

        $titleProperty = $this->prophesize(PropertyInterface::class);
        $titleProperty->getName()->willReturn('title');
        $titleProperty->getValue()->willReturn('test-123');
        $titleProperty->setValue('test-123')->shouldBeCalled();

        $structure->getProperty('title')->willReturn($titleProperty->reveal());

        $titleContentView = $this->prophesize(ContentView::class);
        $titleContentView->getContent()->willReturn('test-123');
        $titleContentView->getView()->willReturn([]);

        // expected service calls
        $this->documentInspector->getMetadata($pageDocument->reveal())
            ->willReturn($pageMetadata->reveal())
            ->shouldBeCalled();

        $this->contentResolver->resolve('test-123', $titleProperty->reveal(), 'en', ['webspaceKey' => 'sulu_io'])
            ->willReturn($titleContentView->reveal());

        $referenceStore = $this->prophesize(ReferenceStoreInterface::class);
        $referenceStore->add('123-123-123')
            ->shouldBeCalled();

        $this->referenceStorePool->getStore('content')
            ->willReturn($referenceStore->reveal())
            ->shouldBeCalled();

        // call test function
        $result = $this->structureResolver->resolveProperties(
            $structure->reveal(),
            ['myTitle' => 'title', 'seoDescription' => 'seo.description', 'excerptTitle' => 'excerpt.title'],
            'en'
        );

        $this->assertSame(
            [
                'id' => '123-123-123',
                'type' => 'page',
                'template' => 'default',
                'content' => [
                    'myTitle' => 'test-123',
                    'seoDescription' => 'seo-description',
                    'excerptTitle' => 'excerpt-title',
                ],
                'view' => [
                    'myTitle' => [],
                    'seoDescription' => [],
                    'excerptTitle' => [],
                ],
                'author' => 1,
                'authored' => $now->format(\DateTimeImmutable::ISO8601),
                'changer' => 3,
                'changed' => $now->format(\DateTimeImmutable::ISO8601),
                'creator' => 2,
                'created' => $now->format(\DateTimeImmutable::ISO8601),
            ],
            $result
        );
    }

    public function testResolvePropertiesIncludeExtension(): void
    {
        $structure = $this->prophesize(StructureBridge::class);
        $pageDocument = $this->prophesize(PageDocument::class);
        $pageMetadata = $this->prophesize(Metadata::class);

        // expected object calls
        $structure->getUuid()->willReturn('123-123-123')->shouldBeCalled();
        $structure->getWebspaceKey()->willReturn('sulu_io')->shouldBeCalled();

        $now = new \DateTimeImmutable();

        $pageDocument->getStructureType()->willReturn('default')->shouldBeCalled();
        $pageDocument->getAuthored()->willReturn($now)->shouldBeCalled();
        $pageDocument->getAuthor()->willReturn(1)->shouldBeCalled();
        $pageDocument->getCreated()->willReturn($now)->shouldBeCalled();
        $pageDocument->getCreator()->willReturn(2)->shouldBeCalled();
        $pageDocument->getChanged()->willReturn($now)->shouldBeCalled();
        $pageDocument->getChanger()->willReturn(3)->shouldBeCalled();
        $pageDocument->getExtensionsData()
            ->willReturn([
                'seo' => [
                    'title' => 'seo-title',
                    'description' => 'seo-description',
                    'noIndex' => false,
                ],
                'excerpt' => [
                    'title' => 'excerpt-title',
                    'categories' => [1, 2, 3],
                    'tags' => [1, 2, 3],
                    'icon' => [1, 2, 3],
                    'images' => [1, 2, 3],
                ],
            ])
            ->shouldBeCalled();

        $pageMetadata->getAlias()
            ->willReturn('page')
            ->shouldBeCalled();

        $structure->getDocument()
            ->willReturn($pageDocument->reveal())
            ->shouldBeCalled();

        $titleProperty = $this->prophesize(PropertyInterface::class);
        $titleProperty->getName()->willReturn('title');
        $titleProperty->getValue()->willReturn('test-123');
        $titleProperty->setValue('test-123')->shouldBeCalled();

        $structure->getProperty('title')->willReturn($titleProperty->reveal());

        $titleContentView = $this->prophesize(ContentView::class);
        $titleContentView->getContent()->willReturn('test-123');
        $titleContentView->getView()->willReturn([]);

        // expected service calls
        $this->documentInspector->getMetadata($pageDocument->reveal())
            ->willReturn($pageMetadata->reveal())
            ->shouldBeCalled();

        $this->contentResolver->resolve('test-123', $titleProperty->reveal(), 'en', ['webspaceKey' => 'sulu_io'])
            ->willReturn($titleContentView->reveal());

        $referenceStore = $this->prophesize(ReferenceStoreInterface::class);
        $referenceStore->add('123-123-123')
            ->shouldBeCalled();

        $this->referenceStorePool->getStore('content')
            ->willReturn($referenceStore->reveal())
            ->shouldBeCalled();

        // call test function
        $result = $this->structureResolver->resolveProperties(
            $structure->reveal(),
            ['myTitle' => 'title'],
            'en',
            true
        );

        $this->assertSame(
            [
                'id' => '123-123-123',
                'type' => 'page',
                'template' => 'default',
                'content' => [
                    'myTitle' => 'test-123',
                ],
                'view' => [
                    'myTitle' => [],
                ],
                'author' => 1,
                'authored' => $now->format(\DateTimeImmutable::ISO8601),
                'changer' => 3,
                'changed' => $now->format(\DateTimeImmutable::ISO8601),
                'creator' => 2,
                'created' => $now->format(\DateTimeImmutable::ISO8601),
                'extension' => [
                    'seo' => [
                        'title' => 'seo-title',
                        'description' => 'seo-description',
                        'noIndex' => false,
                    ],
                    'excerpt' => [
                        'title' => 'excerpt-title',
                    ],
                ],
            ],
            $result
        );
    }
}
