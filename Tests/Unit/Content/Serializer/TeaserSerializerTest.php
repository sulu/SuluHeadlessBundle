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
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\MediaSerializerInterface;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\TeaserSerializer;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\TeaserSerializerInterface;
use Sulu\Bundle\MediaBundle\Entity\MediaInterface;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Sulu\Bundle\PageBundle\Teaser\Teaser;
use Sulu\Bundle\WebsiteBundle\ReferenceStore\ReferenceStoreInterface;
use Sulu\Bundle\WebsiteBundle\ReferenceStore\ReferenceStoreNotExistsException;
use Sulu\Bundle\WebsiteBundle\ReferenceStore\ReferenceStorePoolInterface;
use Sulu\Component\Serializer\ArraySerializerInterface;

class TeaserSerializerTest extends TestCase
{
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
     * @var ReferenceStorePoolInterface|ObjectProphecy
     */
    private $referenceStorePool;

    /**
     * @var TeaserSerializerInterface
     */
    private $teaserSerializer;

    protected function setUp(): void
    {
        $this->arraySerializer = $this->prophesize(ArraySerializerInterface::class);
        $this->mediaSerializer = $this->prophesize(MediaSerializerInterface::class);
        $this->mediaManager = $this->prophesize(MediaManagerInterface::class);
        $this->referenceStorePool = $this->prophesize(ReferenceStorePoolInterface::class);

        $this->teaserSerializer = new TeaserSerializer(
            $this->arraySerializer->reveal(),
            $this->mediaSerializer->reveal(),
            $this->mediaManager->reveal(),
            $this->referenceStorePool->reveal()
        );
    }

    public function testSerialize(): void
    {
        $locale = 'en';

        $teaser = $this->prophesize(Teaser::class);
        $teaser->getId()->willReturn('74a36ca1-4805-48a0-b37d-3ffb3a6be9b1');
        $teaser->getType()->willReturn('pages');
        $teaser->getMediaId()->willReturn(1);

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

        $referenceStore = $this->prophesize(ReferenceStoreInterface::class);
        $this->referenceStorePool->getStore('content')->willReturn($referenceStore->reveal());
        $referenceStore->add('74a36ca1-4805-48a0-b37d-3ffb3a6be9b1')->shouldBeCalled();

        $result = $this->teaserSerializer->serialize($teaser->reveal(), $locale);

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

        $teaser = $this->prophesize(Teaser::class);
        $teaser->getId()->willReturn('5524447a-1afd-4d08-bb25-d34f46e3621c');
        $teaser->getType()->willReturn('articles');
        $teaser->getMediaId()->willReturn(null);

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

        $referenceStore = $this->prophesize(ReferenceStoreInterface::class);
        $this->referenceStorePool->getStore('article')->willReturn($referenceStore->reveal());
        $referenceStore->add('5524447a-1afd-4d08-bb25-d34f46e3621c')->shouldBeCalled();

        $result = $this->teaserSerializer->serialize($teaser->reveal(), $locale);

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

        $teaser = $this->prophesize(Teaser::class);
        $teaser->getId()->willReturn('bb03b2f1-135f-4fcf-b27a-b2cf5f36be66');
        $teaser->getType()->willReturn('other');
        $teaser->getMediaId()->willReturn(null);

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

        $this->referenceStorePool->getStore('other')->willThrow(ReferenceStoreNotExistsException::class);

        $result = $this->teaserSerializer->serialize($teaser->reveal(), $locale, $context->reveal());

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
