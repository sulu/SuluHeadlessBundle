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

use JMS\Serializer\SerializationContext;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\ContactBundle\Api\Account;
use Sulu\Bundle\ContactBundle\Contact\AccountManager;
use Sulu\Bundle\ContactBundle\Entity\AccountInterface;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\AccountSelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\AccountSerializerInterface;
use Sulu\Component\Content\Compat\PropertyInterface;

class AccountSelectionResolverTest extends TestCase
{
    /**
     * @var AccountManager|ObjectProphecy
     */
    private $accountManager;

    /**
     * @var AccountSerializerInterface|ObjectProphecy
     */
    private $accountSerializer;

    /**
     * @var AccountSelectionResolver
     */
    private $accountSelectionResolver;

    protected function setUp(): void
    {
        $this->accountManager = $this->prophesize(AccountManager::class);
        $this->accountSerializer = $this->prophesize(AccountSerializerInterface::class);

        $this->accountSelectionResolver = new AccountSelectionResolver(
            $this->accountManager->reveal(),
            $this->accountSerializer->reveal()
        );
    }

    public function testGetContentType(): void
    {
        self::assertSame('account_selection', $this->accountSelectionResolver::getContentType());
    }

    public function testResolveWithOneAccount(): void
    {
        $locale = 'en';

        $account = $this->prophesize(AccountInterface::class);
        $apiAccount = $this->prophesize(Account::class);
        $apiAccount->getEntity()->willReturn($account->reveal());

        $data = [3];

        $this->accountManager->getByIds($data, $locale)->willReturn([$apiAccount->reveal()]);
        $this->accountSerializer->serialize($account, $locale, Argument::type(SerializationContext::class))->willReturn(
            [
                'id' => 3,
                'depth' => 1,
                'name' => 'Sulu GmbH',
                'corporation' => 'Digital Agency',
                'logo' => [
                    'id' => 2,
                    'formatUri' => '/media/2/{format}/media-2.jpg?v=1-0',
                ],
            ]
        );

        $property = $this->prophesize(PropertyInterface::class);
        $result = $this->accountSelectionResolver->resolve($data, $property->reveal(), $locale);

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame(
            [
                [
                    'id' => 3,
                    'depth' => 1,
                    'name' => 'Sulu GmbH',
                    'corporation' => 'Digital Agency',
                    'logo' => [
                        'id' => 2,
                        'formatUri' => '/media/2/{format}/media-2.jpg?v=1-0',
                    ],
                ],
            ],
            $result->getContent()
        );

        $this->assertSame(
            ['ids' => [3]],
            $result->getView()
        );
    }

    public function testResolveWithManyAccounts(): void
    {
        $locale = 'en';

        $account = $this->prophesize(AccountInterface::class);
        $apiAccount = $this->prophesize(Account::class);
        $apiAccount->getEntity()->willReturn($account->reveal());

        $data = [3, 4, 5];

        $this->accountManager->getByIds($data, $locale)->willReturn([$apiAccount->reveal(), $apiAccount->reveal(), $apiAccount->reveal()]);
        $this->accountSerializer->serialize($account, $locale, Argument::type(SerializationContext::class))->willReturn(
            [
                'id' => 3,
                'depth' => 1,
                'name' => 'Sulu GmbH',
                'corporation' => 'Digital Agency',
                'logo' => [
                    'id' => 2,
                    'formatUri' => '/media/2/{format}/media-2.jpg?v=1-0',
                ],
            ],
            [
                'id' => 4,
                'depth' => 1,
                'name' => 'Test GmbH',
                'corporation' => 'Digital Agency',
                'logo' => [
                    'id' => 3,
                    'formatUri' => '/media/3/{format}/media-2.jpg?v=1-0',
                ],
            ],
            [
                'id' => 5,
                'depth' => 1,
                'name' => 'Test Inc',
                'corporation' => 'Fancy big incorporated',
                'logo' => [
                    'id' => 3,
                    'formatUri' => '/media/3/{format}/media-2.jpg?v=1-0',
                ],
            ]
        );

        $property = $this->prophesize(PropertyInterface::class);
        $result = $this->accountSelectionResolver->resolve($data, $property->reveal(), $locale);

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame(
            [
                [
                    'id' => 3,
                    'depth' => 1,
                    'name' => 'Sulu GmbH',
                    'corporation' => 'Digital Agency',
                    'logo' => [
                        'id' => 2,
                        'formatUri' => '/media/2/{format}/media-2.jpg?v=1-0',
                    ],
                ],
                [
                    'id' => 4,
                    'depth' => 1,
                    'name' => 'Test GmbH',
                    'corporation' => 'Digital Agency',
                    'logo' => [
                        'id' => 3,
                        'formatUri' => '/media/3/{format}/media-2.jpg?v=1-0',
                    ],
                ],
                [
                    'id' => 5,
                    'depth' => 1,
                    'name' => 'Test Inc',
                    'corporation' => 'Fancy big incorporated',
                    'logo' => [
                        'id' => 3,
                        'formatUri' => '/media/3/{format}/media-2.jpg?v=1-0',
                    ],
                ],
            ],
            $result->getContent()
        );

        $this->assertSame(
            ['ids' => [3, 4, 5]],
            $result->getView()
        );
    }

    public function testResolveDataIsNull(): void
    {
        $locale = 'en';
        $property = $this->prophesize(PropertyInterface::class);

        $result = $this->accountSelectionResolver->resolve(null, $property->reveal(), $locale);

        $this->assertNull($result->getContent());

        $this->assertSame([], $result->getView());
    }
}
