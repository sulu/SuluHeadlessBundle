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

namespace Sulu\Bundle\HeadlessBundle\Tests\Unit\Content\Serializer;

use JMS\Serializer\SerializationContext;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\AdminBundle\Teaser\Teaser;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\MediaSerializerInterface;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\TeaserSerializer;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\TeaserSerializerInterface;
use Sulu\Bundle\HttpCacheBundle\ReferenceStore\ReferenceStoreInterface;
use Sulu\Bundle\MediaBundle\Entity\MediaInterface;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Sulu\Component\Serializer\ArraySerializerInterface;

class TeaserSerializerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var ArraySerializerInterface|ObjectProphecy
     */
    private $arraySerializer;

    /**
     * @var MediaSerializerInterface|ObjectProphecy
     */
    private $mediaSerializer;

    /**
     * @var MediaManagerInterface|ObjectProphecy
     */
    private $mediaManager;

    /**
     * @var ReferenceStoreInterface|ObjectProphecy
     */
    private $referenceStore;

    /**
     * @var TeaserSerializerInterface
     */
    private $teaserSerializer;

    protected function setUp(): void
    {
        $this->arraySerializer = $this->prophesize(ArraySerializerInterface::class);
        $this->mediaSerializer = $this->prophesize(MediaSerializerInterface::class);
        $this->mediaManager = $this->prophesize(MediaManagerInterface::class);
        $this->referenceStore = $this->prophesize(ReferenceStoreInterface::class);

        $this->teaserSerializer = new TeaserSerializer(
            $this->arraySerializer->reveal(),
            $this->mediaSerializer->reveal(),
            $this->mediaManager->reveal(),
            $this->referenceStore->reveal()
        );
    }

    public function testSerialize(): void
    {
        $locale = 'en';

        $teaser = new Teaser(
            '74a36ca1-4805-48a0-b37d-3ffb3a6be9b1',
            'pages',
            'en',
            'My page',
            '<p>hello world.</p>',
            'foo',
            '/my-page',
            1,
            [
                'structureType' => 'default',
                'webspaceKey' => 'example',
            ]
        );

        $media = $this->prophesize(MediaInterface::class);
        $this->mediaManager->getEntityById(1)->willReturn($media->reveal());

        $this->arraySerializer->serialize($teaser, null)->willReturn([
            'id' => '74a36ca1-4805-48a0-b37d-3ffb3a6be9b1',
            'type' => 'pages',
            'locale' => 'en',
            'title' => 'My page',
            'description' => '<p>hello world.</p>',
            'moreText' => 'foo',
            'mediaId' => 1,
            'url' => '/my-page',
            'attributes' => [
                'structureType' => 'default',
                'webspaceKey' => 'example',
            ],
        ]);

        $this->mediaSerializer->serialize($media, $locale)->willReturn([
            'id' => 1,
            'formatUri' => '/media/1/{format}/media-1.jpg?=v1-0',
        ]);

        $this->referenceStore->add('74a36ca1-4805-48a0-b37d-3ffb3a6be9b1', 'content')->shouldBeCalled();

        $result = $this->teaserSerializer->serialize($teaser, $locale);

        $this->assertSame([
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
        ], $result);
    }

    public function testSerializeArticleTeaserWithoutMedia(): void
    {
        $locale = 'en';

        $teaser = new Teaser(
            '5524447a-1afd-4d08-bb25-d34f46e3621c',
            'articles',
            'en',
            'My article',
            '<p>hello world.</p>',
            'foo',
            '/my-article',
            0,
            [
                'structureType' => 'default',
                'webspaceKey' => 'example',
            ]
        );

        $this->mediaManager->getEntityById(Argument::any())->shouldNotBeCalled();

        $this->arraySerializer->serialize($teaser, null)->willReturn([
            'id' => '5524447a-1afd-4d08-bb25-d34f46e3621c',
            'type' => 'articles',
            'locale' => 'en',
            'title' => 'My article',
            'description' => '<p>hello world.</p>',
            'moreText' => 'foo',
            'mediaId' => null,
            'url' => '/my-article',
            'attributes' => [
                'structureType' => 'default',
                'webspaceKey' => 'example',
            ],
        ]);

        $this->mediaSerializer->serialize(Argument::any())->shouldNotBeCalled();

        $this->referenceStore->add('5524447a-1afd-4d08-bb25-d34f46e3621c', 'article')->shouldBeCalled();

        $result = $this->teaserSerializer->serialize($teaser, $locale);

        $this->assertSame([
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
        ], $result);
    }

    public function testSerializeOtherTeaserWithContext(): void
    {
        $locale = 'en';
        $context = $this->prophesize(SerializationContext::class);

        $teaser = new Teaser(
            'bb03b2f1-135f-4fcf-b27a-b2cf5f36be66',
            'other',
            'en',
            'My thing',
            '<p>hello world.</p>',
            'foo',
            '/my-thing',
            0,
            []
        );

        $this->arraySerializer->serialize($teaser, $context)->willReturn([
            'id' => 'bb03b2f1-135f-4fcf-b27a-b2cf5f36be66',
            'type' => 'other',
            'locale' => 'en',
            'title' => 'My thing',
            'description' => '<p>hello world.</p>',
            'moreText' => 'foo',
            'mediaId' => null,
            'url' => '/my-thing',
        ]);

        $this->referenceStore->add('bb03b2f1-135f-4fcf-b27a-b2cf5f36be66', 'other')->shouldBeCalled();

        $result = $this->teaserSerializer->serialize($teaser, $locale, $context->reveal());

        $this->assertSame([
            'id' => 'bb03b2f1-135f-4fcf-b27a-b2cf5f36be66',
            'type' => 'other',
            'locale' => 'en',
            'title' => 'My thing',
            'description' => '<p>hello world.</p>',
            'moreText' => 'foo',
            'url' => '/my-thing',
            'media' => null,
        ], $result);
    }
}
