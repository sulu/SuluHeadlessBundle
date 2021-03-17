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
use Sulu\Bundle\ContactBundle\Contact\AccountManager;
use Sulu\Bundle\ContactBundle\Entity\AccountInterface;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\AccountSerializer;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\AccountSerializerInterface;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\MediaSerializerInterface;
use Sulu\Bundle\MediaBundle\Api\Media;
use Sulu\Bundle\MediaBundle\Entity\MediaInterface;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Sulu\Bundle\WebsiteBundle\ReferenceStore\ReferenceStoreInterface;
use Sulu\Component\Serializer\ArraySerializerInterface;

class AccountSerializerTest extends TestCase
{
    /**
     * @var AccountManager|ObjectProphecy
     */
    private $accountManager;

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
     * @var AccountSerializerInterface
     */
    private $accountSerializer;

    protected function setUp(): void
    {
        $this->accountManager = $this->prophesize(AccountManager::class);
        $this->arraySerializer = $this->prophesize(ArraySerializerInterface::class);
        $this->mediaSerializer = $this->prophesize(MediaSerializerInterface::class);
        $this->mediaManager = $this->prophesize(MediaManagerInterface::class);
        $this->referenceStore = $this->prophesize(ReferenceStoreInterface::class);

        $this->accountSerializer = new AccountSerializer(
            $this->accountManager->reveal(),
            $this->arraySerializer->reveal(),
            $this->mediaSerializer->reveal(),
            $this->mediaManager->reveal(),
            $this->referenceStore->reveal()
        );
    }

    public function testSerialize(): void
    {
        $locale = 'en';
        $account = $this->prophesize(AccountInterface::class);

        // expected and unexpected object calls
        $account->getId()
            ->willReturn(1)
            ->shouldBeCalled();

        $apiAccount = $this->prophesize(Account::class);
        $apiAccount->getNote()->willReturn('test-note')->shouldBeCalled();
        $apiAccount->getLogo()->willReturn([
            'id' => 1,
            'url' => '/media/1/download/sulu.png?v=1',
        ])->shouldBeCalled();

        $media = $this->prophesize(MediaInterface::class);
        $apiMedia = $this->prophesize(Media::class);
        $apiMedia->getEntity()->willReturn($media->reveal())->shouldBeCalled();

        // expected and unexpected service calls
        $this->accountManager->getAccount($account->reveal(), $locale)
            ->willReturn($apiAccount->reveal())
            ->shouldBeCalled();

        $this->arraySerializer->serialize($apiAccount, null)
            ->willReturn([
                'id' => 1,
                'depth' => 1,
                'name' => 'Sulu GmbH',
                'corporation' => 'Digital Agency',
            ])
            ->shouldBeCalled();

        $this->mediaManager->getById(1, $locale)
            ->willReturn($apiMedia->reveal())
            ->shouldBeCalled();

        $this->mediaSerializer->serialize($media, $locale, null)
            ->willReturn([
                'id' => 1,
                'formatUri' => '/media/1/{format}/media-2.jpg?v=1-0',
            ])
            ->shouldBeCalled();

        $this->referenceStore->add(1)
            ->shouldBeCalled();

        // call test function
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
        $account = $this->prophesize(AccountInterface::class);
        $context = $this->prophesize(SerializationContext::class);

        // expected and unexpected object calls
        $account->getId()
            ->willReturn(1)
            ->shouldBeCalled();

        $apiAccount = $this->prophesize(Account::class);
        $apiAccount->getNote()->willReturn(null)->shouldBeCalled();
        $apiAccount->getLogo()->willReturn([
            'id' => 2,
            'url' => '/media/2/download/sulu.png?v=1',
        ])->shouldBeCalled();

        $media = $this->prophesize(MediaInterface::class);
        $apiMedia = $this->prophesize(Media::class);
        $apiMedia->getEntity()->willReturn($media->reveal())->shouldBeCalled();

        // expected and unexpected service calls
        $this->accountManager->getAccount($account->reveal(), $locale)
            ->willReturn($apiAccount->reveal())
            ->shouldBeCalled();

        $this->arraySerializer->serialize($apiAccount, $context)
            ->willReturn([
                'id' => 1,
                'depth' => 1,
                'name' => 'Sulu GmbH',
                'corporation' => 'Digital Agency',
            ])
            ->shouldBeCalled();

        $this->mediaManager->getById(2, $locale)
            ->willReturn($apiMedia->reveal())
            ->shouldBeCalled();

        $this->mediaSerializer->serialize($media, $locale)
            ->willReturn([
                'id' => 2,
                'formatUri' => '/media/2/{format}/media-2.jpg?v=1-0',
            ])
            ->shouldBeCalled();

        $this->referenceStore->add(1)
            ->shouldBeCalled();

        // call test function
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
        $account = $this->prophesize(AccountInterface::class);
        $context = $this->prophesize(SerializationContext::class);

        // expected and unexpected object calls
        $account->getId()
            ->willReturn(1)
            ->shouldBeCalled();

        $apiAccount = $this->prophesize(Account::class);
        $apiAccount->getNote()->willReturn(null)->shouldBeCalled();
        $apiAccount->getLogo()->willReturn(null)->shouldBeCalled();

        // expected and unexpected service calls
        $this->accountManager->getAccount($account->reveal(), $locale)
            ->willReturn($apiAccount->reveal())
            ->shouldBeCalled();

        $this->arraySerializer->serialize($apiAccount, $context)
            ->willReturn([
                'id' => 1,
                'depth' => 1,
                'name' => 'Sulu GmbH',
                'corporation' => 'Digital Agency',
            ])->shouldBeCalled();

        $this->mediaManager->getById(Argument::cetera())
            ->shouldNotBeCalled();

        $this->mediaSerializer->serialize(Argument::cetera())
            ->shouldNotBeCalled();

        $this->referenceStore->add(1)
            ->shouldBeCalled();

        // call test function
        $result = $this->accountSerializer->serialize($account->reveal(), $locale, $context->reveal());

        $this->assertSame([
            'id' => 1,
            'depth' => 1,
            'name' => 'Sulu GmbH',
            'corporation' => 'Digital Agency',
        ], $result);
    }
}
