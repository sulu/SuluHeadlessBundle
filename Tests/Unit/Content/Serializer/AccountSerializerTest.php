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
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\ContactBundle\Api\Account;
use Sulu\Bundle\ContactBundle\Contact\AccountFactoryInterface;
use Sulu\Bundle\ContactBundle\Entity\AccountInterface;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\AccountSerializer;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\AccountSerializerInterface;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\MediaSerializerInterface;
use Sulu\Bundle\MediaBundle\Api\Media;
use Sulu\Bundle\MediaBundle\Entity\MediaInterface;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Sulu\Component\Serializer\ArraySerializerInterface;

class AccountSerializerTest extends TestCase
{
    /**
     * @var AccountFactoryInterface|ObjectProphecy
     */
    private $accountFactory;

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
     * @var AccountSerializerInterface
     */
    private $accountSerializer;

    protected function setUp(): void
    {
        $this->accountFactory = $this->prophesize(AccountFactoryInterface::class);
        $this->arraySerializer = $this->prophesize(ArraySerializerInterface::class);
        $this->mediaSerializer = $this->prophesize(MediaSerializerInterface::class);
        $this->mediaManager = $this->prophesize(MediaManagerInterface::class);

        $this->accountSerializer = new AccountSerializer(
            $this->accountFactory->reveal(),
            $this->arraySerializer->reveal(),
            $this->mediaSerializer->reveal(),
            $this->mediaManager->reveal()
        );
    }

    public function testSerialize(): void
    {
        $locale = 'en';

        $apiAccount = $this->prophesize(Account::class);
        $apiAccount->getNote()->willReturn('test-note');
        $apiAccount->getLogo()->willReturn([
            'id' => 1,
            'url' => '/media/1/download/sulu.png?v=1',
        ]);

        $account = $this->prophesize(AccountInterface::class);
        $this->accountFactory->createApiEntity($account->reveal(), $locale)->willReturn($apiAccount->reveal());

        $media = $this->prophesize(MediaInterface::class);
        $apiMedia = $this->prophesize(Media::class);
        $apiMedia->getEntity()->willReturn($media->reveal());

        $this->arraySerializer->serialize($apiAccount, null)->willReturn([
            'id' => 1,
            'depth' => 1,
            'name' => 'Sulu GmbH',
            'corporation' => 'Digital Agency',
        ]);

        $this->mediaSerializer->serialize($media, $locale, null)->willReturn([
            'id' => 1,
            'formatUri' => '/media/1/{format}/media-2.jpg?v=1-0',
        ]);

        $this->mediaManager->getById(1, $locale)->shouldBeCalled()->willReturn($apiMedia->reveal());

        $result = $this->accountSerializer->serialize($account->reveal(), $locale, null);

        $this->assertSame([
            'id' => 1,
            'depth' => 1,
            'name' => 'Sulu GmbH',
            'corporation' => 'Digital Agency',
            'note' => 'test-note',
            'logo' => [
                'id' => 1,
                'formatUri' => '/media/1/{format}/media-2.jpg?v=1-0',
            ],
        ], $result);
    }

    public function testSerializeWithContext(): void
    {
        $locale = 'en';

        $apiAccount = $this->prophesize(Account::class);
        $apiAccount->getNote()->willReturn(null);
        $apiAccount->getLogo()->willReturn([
            'id' => 2,
            'url' => '/media/2/download/sulu.png?v=1',
        ]);

        $account = $this->prophesize(AccountInterface::class);
        $this->accountFactory->createApiEntity($account->reveal(), $locale)->willReturn($apiAccount->reveal());

        $media = $this->prophesize(MediaInterface::class);
        $apiMedia = $this->prophesize(Media::class);
        $apiMedia->getEntity()->willReturn($media->reveal());

        $context = $this->prophesize(SerializationContext::class);

        $this->arraySerializer->serialize($apiAccount, $context)->willReturn([
            'id' => 1,
            'depth' => 1,
            'name' => 'Sulu GmbH',
            'corporation' => 'Digital Agency',
        ]);

        $this->mediaSerializer->serialize($media, $locale)->willReturn([
            'id' => 2,
            'formatUri' => '/media/2/{format}/media-2.jpg?v=1-0',
        ]);

        $this->mediaManager->getById(2, $locale)->shouldBeCalled()->willReturn($apiMedia->reveal());

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

        $apiAccount = $this->prophesize(Account::class);
        $apiAccount->getNote()->willReturn(null);
        $apiAccount->getLogo()->willReturn(null);

        $account = $this->prophesize(AccountInterface::class);
        $this->accountFactory->createApiEntity($account->reveal(), $locale)->willReturn($apiAccount->reveal());

        $context = $this->prophesize(SerializationContext::class);

        $this->arraySerializer->serialize($apiAccount, $context)->willReturn([
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
