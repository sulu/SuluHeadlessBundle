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

namespace Sulu\Bundle\HeadlessBundle\Tests\Unit\Content\ContentTypeResolver;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\TeaserSelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\TeaserSerializerInterface;
use Sulu\Bundle\PageBundle\Teaser\Teaser;
use Sulu\Bundle\PageBundle\Teaser\TeaserManagerInterface;
use Sulu\Component\Content\Compat\PropertyInterface;

class TeaserSelectionResolverTest extends TestCase
{
    /**
     * @var TeaserManagerInterface|ObjectProphecy
     */
    private $teaserManager;

    /**
     * @var TeaserSerializerInterface|ObjectProphecy
     */
    private $teaserSerializer;

    /**
     * @var TeaserSelectionResolver
     */
    private $teaserSelectionResolver;

    protected function setUp(): void
    {
        $this->teaserManager = $this->prophesize(TeaserManagerInterface::class);
        $this->teaserSerializer = $this->prophesize(TeaserSerializerInterface::class);

        $this->teaserSelectionResolver = new TeaserSelectionResolver(
            $this->teaserManager->reveal(),
            $this->teaserSerializer->reveal()
        );
    }

    public function testGetContentType(): void
    {
        self::assertSame('teaser_selection', $this->teaserSelectionResolver::getContentType());
    }

    public function testResolve(): void
    {
        $locale = 'en';
        $items = [
            [
                'id' => '74a36ca1-4805-48a0-b37d-3ffb3a6be9b1',
                'type' => 'pages',
            ],
            [
                'id' => '5524447a-1afd-4d08-bb25-d34f46e3621c',
                'type' => 'articles',
            ],
            [
                'id' => 'bb03b2f1-135f-4fcf-b27a-b2cf5f36be66',
                'type' => 'other',
            ],
        ];
        $value = [
            'presentAs' => 'two-columns',
            'items' => $items,
        ];

        /** @var PropertyInterface|ObjectProphecy $property */
        $property = $this->prophesize(PropertyInterface::class);

        $pageTeaser = $this->prophesize(Teaser::class);
        $articleTeaser = $this->prophesize(Teaser::class);
        $otherTeaser = $this->prophesize(Teaser::class);

        $this->teaserManager->find($items, $locale)->willReturn([
            $pageTeaser->reveal(),
            $articleTeaser->reveal(),
            $otherTeaser->reveal(),
        ]);

        $this->teaserSerializer->serialize($pageTeaser->reveal(), $locale)->willReturn([
            'id' => '74a36ca1-4805-48a0-b37d-3ffb3a6be9b1',
            'type' => 'pages',
            'locale' => 'en',
            'title' => 'My page',
            'description' => '<p>hello world.</p>',
            'moreText' => 'foo',
            'url' => '/my-page',
            'attributes' => [
                'structureType' => 'default',
                'webspaceKey' => 'example',
            ],
            'media' => [
                'id' => 1,
                'formatUri' => '/media/1/{format}/media-1.jpg?=v1-0',
            ],
        ]);

        $this->teaserSerializer->serialize($articleTeaser->reveal(), $locale)->willReturn([
            'id' => '5524447a-1afd-4d08-bb25-d34f46e3621c',
            'type' => 'articles',
            'locale' => 'en',
            'title' => 'My article',
            'description' => '<p>hello world.</p>',
            'moreText' => 'foo',
            'url' => '/my-article',
            'attributes' => [
                'structureType' => 'default',
                'webspaceKey' => 'example',
            ],
            'media' => null,
        ]);

        $this->teaserSerializer->serialize($otherTeaser->reveal(), $locale)->willReturn([
            'id' => 'bb03b2f1-135f-4fcf-b27a-b2cf5f36be66',
            'type' => 'other',
            'locale' => 'en',
            'title' => 'My thing',
            'description' => '<p>hello world.</p>',
            'moreText' => 'foo',
            'url' => '/my-thing',
            'media' => null,
        ]);

        $result = $this->teaserSelectionResolver->resolve($value, $property->reveal(), $locale);

        self::assertSame([
            [
                'id' => '74a36ca1-4805-48a0-b37d-3ffb3a6be9b1',
                'type' => 'pages',
                'locale' => 'en',
                'title' => 'My page',
                'description' => '<p>hello world.</p>',
                'moreText' => 'foo',
                'url' => '/my-page',
                'attributes' => [
                    'structureType' => 'default',
                    'webspaceKey' => 'example',
                ],
                'media' => [
                    'id' => 1,
                    'formatUri' => '/media/1/{format}/media-1.jpg?=v1-0',
                ],
            ],
            [
                'id' => '5524447a-1afd-4d08-bb25-d34f46e3621c',
                'type' => 'articles',
                'locale' => 'en',
                'title' => 'My article',
                'description' => '<p>hello world.</p>',
                'moreText' => 'foo',
                'url' => '/my-article',
                'attributes' => [
                    'structureType' => 'default',
                    'webspaceKey' => 'example',
                ],
                'media' => null,
            ],
            [
                'id' => 'bb03b2f1-135f-4fcf-b27a-b2cf5f36be66',
                'type' => 'other',
                'locale' => 'en',
                'title' => 'My thing',
                'description' => '<p>hello world.</p>',
                'moreText' => 'foo',
                'url' => '/my-thing',
                'media' => null,
            ],
        ], $result->getContent());
        self::assertSame($value, $result->getView());
    }

    public function testResolveNullValue(): void
    {
        $locale = 'en';
        $value = null;

        /** @var PropertyInterface|ObjectProphecy $property */
        $property = $this->prophesize(PropertyInterface::class);

        $this->teaserManager->find(Argument::any())->shouldNotBeCalled();
        $this->teaserSerializer->serialize(Argument::any())->shouldNotBeCalled();

        $result = $this->teaserSelectionResolver->resolve($value, $property->reveal(), $locale);

        self::assertSame([], $result->getContent());
        self::assertSame([
            'presentAs' => null,
            'items' => [],
        ], $result->getView());
    }
}
