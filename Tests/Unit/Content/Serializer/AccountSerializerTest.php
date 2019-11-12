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
use Sulu\Bundle\ContactBundle\Api\Account;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\AccountSerializer;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\MediaSerializer;
use Sulu\Bundle\MediaBundle\Api\Media;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Sulu\Component\Serializer\ArraySerializerInterface;

class AccountSerializerTest extends TestCase
{
    /**
     * @var ArraySerializerInterface|ObjectProphecy
     */
    private $arraySerializer;

    /**
     * @var MediaSerializer|ObjectProphecy
     */
    private $mediaSerializer;

    /**
     * @var MediaManagerInterface|ObjectProphecy
     */
    private $mediaManager;

    /**
     * @var AccountSerializer
     */
    private $accountSerializer;

    protected function setUp(): void
    {
        $this->arraySerializer = $this->prophesize(ArraySerializerInterface::class);
        $this->mediaSerializer = $this->prophesize(MediaSerializer::class);
        $this->mediaManager = $this->prophesize(MediaManagerInterface::class);

        $this->accountSerializer = new AccountSerializer(
            $this->arraySerializer->reveal(),
            $this->mediaSerializer->reveal(),
            $this->mediaManager->reveal()
        );
    }

    public function testSerialize(): void
    {
        $locale = 'en';
        $account = $this->prophesize(Account::class);
        $account->getLogo()->willReturn([
            'id' => 1,
            'url' => '/media/2/download/sulu.png?v=1',
        ]);

        $media = $this->prophesize(Media::class);
        $media->getId()->willReturn(2);
        $media->getName()->willReturn('media-2.png');
        $media->getMimeType()->willReturn('image/jpg');
        $media->getVersion()->willReturn(1);
        $media->getSubVersion()->willReturn(0);

        $this->arraySerializer->serialize($account, null)->willReturn([
            'id' => 1,
            'depth' => 1,
            'name' => 'Sulu GmbH',
            'corporation' => 'Digital Agency',
            'logo' => [
                'id' => 2,
                'url' => '/media/2/download/media-2.jpg',
            ],
        ]);

        $this->mediaSerializer->serialize($media, null)->willReturn([
            'id' => 2,
            'formatUri' => '/media/2/{format}/media-2.jpg?v=1-0',
        ]);

        $this->mediaManager->getById(Argument::any(), $locale)->shouldBeCalled()->willReturn($media->reveal());

        $result = $this->accountSerializer->serialize($account->reveal(), $locale, null);

        $this->assertSame([
            'id' => 1,
            'depth' => 1,
            'name' => 'Sulu GmbH',
            'corporation' => 'Digital Agency',
            'logo' => [
                'id' => 2,
                'formatUri' => '/media/2/{format}/media-2.jpg?v=1-0',
            ],
        ], $result);
    }

    public function testSerializeWithContext(): void
    {
        $locale = 'en';
        $account = $this->prophesize(Account::class);
        $account->getLogo()->willReturn([
            'id' => 2,
            'url' => '/media/2/download/sulu.png?v=1',
        ]);

        $media = $this->prophesize(Media::class);
        $media->getId()->willReturn(1);
        $media->getName()->willReturn('media-1.png');
        $media->getMimeType()->willReturn('image/png');
        $media->getVersion()->willReturn(1);
        $media->getSubVersion()->willReturn(0);

        $context = $this->prophesize(SerializationContext::class);

        $this->arraySerializer->serialize($account, $context)->willReturn([
            'id' => 1,
            'depth' => 1,
            'name' => 'Sulu GmbH',
            'corporation' => 'Digital Agency',
            'logo' => [
                'id' => 2,
                'url' => '/media/2/download/sulu.png?v=1',
            ],
        ]);

        $this->mediaSerializer->serialize($media)->willReturn([
            'id' => 2,
            'formatUri' => '/media/2/{format}/media-2.jpg?v=1-0',
        ]);

        $this->mediaManager->getById(Argument::any(), $locale)->shouldBeCalled()->willReturn($media->reveal());

        $result = $this->accountSerializer->serialize($account->reveal(), $locale, $context->reveal());

        $this->assertSame([
            'id' => 1,
            'depth' => 1,
            'name' => 'Sulu GmbH',
            'corporation' => 'Digital Agency',
            'logo' => [
                'id' => 2,
                'formatUri' => '/media/2/{format}/media-2.jpg?v=1-0',
            ],
        ], $result);
    }

    public function testSerializeWithoutLogo(): void
    {
        $locale = 'en';
        $account = $this->prophesize(Account::class);
        $account->getLogo()->willReturn(null);

        $context = $this->prophesize(SerializationContext::class);

        $this->arraySerializer->serialize($account, $context)->willReturn([
            'id' => 1,
            'depth' => 1,
            'name' => 'Sulu GmbH',
            'corporation' => 'Digital Agency',
        ]);

        $result = $this->accountSerializer->serialize($account->reveal(), $locale, $context->reveal());

        $this->assertSame([
            'id' => 1,
            'depth' => 1,
            'name' => 'Sulu GmbH',
            'corporation' => 'Digital Agency',
        ], $result);
    }
}
