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
use Sulu\Bundle\PageBundle\Document\BasePageDocument;
use Sulu\Bundle\PageBundle\Document\HomeDocument;
use Sulu\Bundle\PageBundle\Document\PageDocument;
use Sulu\Component\Content\Compat\PropertyInterface;
use Sulu\Component\Content\Compat\Structure\StructureBridge;
use Sulu\Component\Content\Compat\StructureManagerInterface;
use Sulu\Component\DocumentManager\Metadata;

class StructureResolverTest extends TestCase
{
    /**
     * @var StructureBridge|ObjectProphecy
     */
    private $structure;

    /**
     * @var StructureBridge|ObjectProphecy
     */
    private $excerpt;

    /**
     * @var BasePageDocument|ObjectProphecy
     */
    private $pageDocument;

    /**
     * @var BasePageDocument|ObjectProphecy
     */
    private $homepageDocument;

    /**
     * @var Metadata|ObjectProphecy
     */
    private $homepageMetadata;

    /**
     * @var Metadata|ObjectProphecy
     */
    private $pageMetadata;

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
     * @var StructureResolver
     */
    private $structureResolver;

    protected function setUp(): void
    {
        $this->structure = $this->prophesize(StructureBridge::class);
        $this->pageDocument = $this->prophesize(PageDocument::class);
        $this->homepageDocument = $this->prophesize(HomeDocument::class);

        $this->contentResolver = $this->prophesize(ContentResolverInterface::class);
        $this->structureManager = $this->prophesize(StructureManagerInterface::class);
        $this->documentInspector = $this->prophesize(DocumentInspector::class);

        $this->excerpt = $this->prophesizeExcerpt();
        $this->structureManager->getStructure('excerpt')->willReturn($this->excerpt->reveal());

        $this->homepageMetadata = $this->prophesize(Metadata::class);
        $this->homepageMetadata->getAlias()->willReturn('home');
        $this->documentInspector->getMetadata($this->homepageDocument->reveal())
            ->willReturn($this->homepageMetadata->reveal());

        $this->pageMetadata = $this->prophesize(Metadata::class);
        $this->pageMetadata->getAlias()->willReturn('page');
        $this->documentInspector->getMetadata($this->pageDocument->reveal())
            ->willReturn($this->pageMetadata->reveal());

        $this->structureResolver = new StructureResolver(
            $this->contentResolver->reveal(),
            $this->structureManager->reveal(),
            $this->documentInspector->reveal()
        );
    }

    private function prophesizeExcerpt(): ObjectProphecy
    {
        $excerpt = $this->prophesize(StructureBridge::class);

        $titleProperty = $this->prophesize(PropertyInterface::class);
        $titleProperty->getName()->willReturn('title');
        $titleProperty->setValue(Argument::any())->willReturn();

        $excerpt->getProperties(true)->willReturn([$titleProperty->reveal()]);
        $excerpt->getProperty('title')->willReturn($titleProperty->reveal());

        $this->contentResolver->resolve(Argument::cetera())->will(
            function ($arguments) {
                return new ContentView($arguments[0]);
            }
        );

        return $excerpt;
    }

    public function testResolvePage(): void
    {
        $this->structure->getDocument()->willReturn($this->pageDocument->reveal());

        $now = new \DateTimeImmutable();

        $this->structure->getUuid()->willReturn('123-123-123');
        $this->structure->getWebspaceKey()->willReturn('sulu_io');
        $this->structure->getLanguageCode()->willReturn('en');

        $this->pageDocument->getStructureType()->willReturn('default');
        $this->pageDocument->getAuthored()->willReturn($now);
        $this->pageDocument->getAuthor()->willReturn(1);
        $this->pageDocument->getCreated()->willReturn($now);
        $this->pageDocument->getCreator()->willReturn(2);
        $this->pageDocument->getChanged()->willReturn($now);
        $this->pageDocument->getChanger()->willReturn(3);
        $this->pageDocument->getExtensionsData()->willReturn([
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
        ]);

        $titleProperty = $this->prophesize(PropertyInterface::class);
        $titleProperty->getName()->willReturn('title');
        $titleProperty->getValue()->willReturn('test-123');
        $mediaProperty = $this->prophesize(PropertyInterface::class);
        $mediaProperty->getName()->willReturn('media');
        $mediaProperty->getValue()->willReturn(['ids' => [1, 2, 3]]);

        $this->structure->getProperties(true)->willReturn(
            [
                $titleProperty->reveal(),
                $mediaProperty->reveal(),
            ]
        );

        $contentView1 = $this->prophesize(ContentView::class);
        $contentView1->getContent()->willReturn('test-123');
        $contentView1->getView()->willReturn([]);

        $this->contentResolver->resolve('test-123', $titleProperty->reveal(), 'en', ['webspaceKey' => 'sulu_io'])
            ->willReturn($contentView1->reveal());

        $contentView2 = $this->prophesize(ContentView::class);
        $contentView2->getContent()->willReturn(['media1', 'media2', 'media3']);
        $contentView2->getView()->willReturn(['ids' => [1, 2, 3]]);

        $this->contentResolver->resolve(
            ['ids' => [1, 2, 3]],
            $mediaProperty->reveal(),
            'en',
            ['webspaceKey' => 'sulu_io']
        )->willReturn($contentView2->reveal());

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
            $this->structureResolver->resolve($this->structure->reveal(), 'en')
        );
    }

    public function testResolveHomepage(): void
    {
        $this->structure->getDocument()->willReturn($this->homepageDocument->reveal());

        $now = new \DateTimeImmutable();

        $this->structure->getUuid()->willReturn('123-123-123');
        $this->structure->getParent()->shouldNotBeCalled();
        $this->structure->getWebspaceKey()->willReturn('sulu_io');
        $this->structure->getLanguageCode()->willReturn('en');

        $this->homepageDocument->getStructureType()->willReturn('default');
        $this->homepageDocument->getAuthored()->willReturn($now);
        $this->homepageDocument->getAuthor()->willReturn(1);
        $this->homepageDocument->getCreated()->willReturn($now);
        $this->homepageDocument->getCreator()->willReturn(2);
        $this->homepageDocument->getChanged()->willReturn($now);
        $this->homepageDocument->getChanger()->willReturn(3);
        $this->homepageDocument->getExtensionsData()->willReturn([]);

        $titleProperty = $this->prophesize(PropertyInterface::class);
        $titleProperty->getName()->willReturn('title');
        $titleProperty->getValue()->willReturn('test-123');
        $mediaProperty = $this->prophesize(PropertyInterface::class);
        $mediaProperty->getName()->willReturn('media');
        $mediaProperty->getValue()->willReturn(['ids' => [1, 2, 3]]);

        $this->structure->getProperties(true)->willReturn(
            [
                $titleProperty->reveal(),
                $mediaProperty->reveal(),
            ]
        );

        $contentView1 = $this->prophesize(ContentView::class);
        $contentView1->getContent()->willReturn('test-123');
        $contentView1->getView()->willReturn([]);

        $this->contentResolver->resolve('test-123', $titleProperty->reveal(), 'en', ['webspaceKey' => 'sulu_io'])
            ->willReturn($contentView1->reveal());

        $contentView2 = $this->prophesize(ContentView::class);
        $contentView2->getContent()->willReturn(['media1', 'media2', 'media3']);
        $contentView2->getView()->willReturn(['ids' => [1, 2, 3]]);

        $this->contentResolver->resolve(
            ['ids' => [1, 2, 3]],
            $mediaProperty->reveal(),
            'en',
            ['webspaceKey' => 'sulu_io']
        )->willReturn($contentView2->reveal());

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
            $this->structureResolver->resolve($this->structure->reveal(), 'en')
        );
    }

    public function testResolveProperties(): void
    {
        $this->structure->getDocument()->willReturn($this->pageDocument->reveal());

        $now = new \DateTimeImmutable();

        $this->structure->getUuid()->willReturn('123-123-123');
        $this->structure->getWebspaceKey()->willReturn('sulu_io');
        $this->structure->getLanguageCode()->willReturn('en');

        $this->pageDocument->getStructureType()->willReturn('default');
        $this->pageDocument->getAuthored()->willReturn($now);
        $this->pageDocument->getAuthor()->willReturn(1);
        $this->pageDocument->getCreated()->willReturn($now);
        $this->pageDocument->getCreator()->willReturn(2);
        $this->pageDocument->getChanged()->willReturn($now);
        $this->pageDocument->getChanger()->willReturn(3);
        $this->pageDocument->getExtensionsData()->willReturn([
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
        ]);

        $titleProperty = $this->prophesize(PropertyInterface::class);
        $titleProperty->getName()->willReturn('title');
        $titleProperty->getValue()->willReturn('test-123');
        $titleProperty->setValue('test-123')->shouldBeCalled();

        $this->structure->getProperty('title')->willReturn($titleProperty->reveal());

        $contentView1 = $this->prophesize(ContentView::class);
        $contentView1->getContent()->willReturn('test-123');
        $contentView1->getView()->willReturn([]);

        $this->contentResolver->resolve('test-123', $titleProperty->reveal(), 'en', ['webspaceKey' => 'sulu_io'])
            ->willReturn($contentView1->reveal());

        $result = $this->structureResolver->resolveProperties(
            $this->structure->reveal(),
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
        $this->structure->getDocument()->willReturn($this->pageDocument->reveal());

        $now = new \DateTimeImmutable();

        $this->structure->getUuid()->willReturn('123-123-123');
        $this->structure->getWebspaceKey()->willReturn('sulu_io');
        $this->structure->getLanguageCode()->willReturn('en');

        $this->pageDocument->getStructureType()->willReturn('default');
        $this->pageDocument->getAuthored()->willReturn($now);
        $this->pageDocument->getAuthor()->willReturn(1);
        $this->pageDocument->getCreated()->willReturn($now);
        $this->pageDocument->getCreator()->willReturn(2);
        $this->pageDocument->getChanged()->willReturn($now);
        $this->pageDocument->getChanger()->willReturn(3);
        $this->pageDocument->getExtensionsData()->willReturn([
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
        ]);

        $titleProperty = $this->prophesize(PropertyInterface::class);
        $titleProperty->getName()->willReturn('title');
        $titleProperty->getValue()->willReturn('test-123');
        $titleProperty->setValue('test-123')->shouldBeCalled();

        $this->structure->getProperty('title')->willReturn($titleProperty->reveal());

        $contentView1 = $this->prophesize(ContentView::class);
        $contentView1->getContent()->willReturn('test-123');
        $contentView1->getView()->willReturn([]);

        $this->contentResolver->resolve('test-123', $titleProperty->reveal(), 'en', ['webspaceKey' => 'sulu_io'])
            ->willReturn($contentView1->reveal());

        $result = $this->structureResolver->resolveProperties(
            $this->structure->reveal(),
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
