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

namespace Sulu\Bundle\HeadlessBundle\Tests\Unit\Content\DataProviderResolver;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\AdminBundle\SmartContent\Configuration\ProviderConfigurationInterface;
use Sulu\Bundle\AdminBundle\SmartContent\SmartContentProviderInterface;
use Sulu\Bundle\ContactBundle\Entity\AccountInterface;
use Sulu\Bundle\ContactBundle\Entity\AccountRepositoryInterface;
use Sulu\Bundle\HeadlessBundle\Content\DataProviderResolver\AccountDataProviderResolver;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\AccountSerializerInterface;

class AccountDataProviderResolverTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var ObjectProphecy<SmartContentProviderInterface>
     */
    private ObjectProphecy $accountSmartContentProvider;

    /**
     * @var ObjectProphecy<AccountSerializerInterface>
     */
    private ObjectProphecy $accountSerializer;

    /**
     * @var ObjectProphecy<AccountRepositoryInterface>
     */
    private ObjectProphecy $accountRepository;

    private AccountDataProviderResolver $accountResolver;

    protected function setUp(): void
    {
        $this->accountSmartContentProvider = $this->prophesize(SmartContentProviderInterface::class);
        $this->accountSerializer = $this->prophesize(AccountSerializerInterface::class);
        $this->accountRepository = $this->prophesize(AccountRepositoryInterface::class);

        $this->accountResolver = new AccountDataProviderResolver(
            $this->accountSmartContentProvider->reveal(),
            $this->accountSerializer->reveal(),
            $this->accountRepository->reveal(),
        );
    }

    public function testGetDataProvider(): void
    {
        self::assertSame('accounts', $this->accountResolver::getDataProvider());
    }

    public function testGetProviderConfiguration(): void
    {
        $configuration = $this->prophesize(ProviderConfigurationInterface::class);
        $this->accountSmartContentProvider->getConfiguration()->willReturn($configuration->reveal());

        $this->assertSame($configuration->reveal(), $this->accountResolver->getProviderConfiguration());
    }

    public function testGetProviderDefaultParams(): void
    {
        $this->assertSame([], $this->accountResolver->getProviderDefaultParams());
    }

    public function testResolve(): void
    {
        $account1 = $this->prophesize(AccountInterface::class);
        $account1->getId()->willReturn(1);
        $account2 = $this->prophesize(AccountInterface::class);
        $account2->getId()->willReturn(2);

        // SmartContentProvider returns flat results with id/title
        $this->accountSmartContentProvider->findFlatBy(
            Argument::type('array'),
            Argument::type('array'),
            ['locale' => 'en']
        )->willReturn([
            ['id' => '1', 'title' => 'Account 1'],
            ['id' => '2', 'title' => 'Account 2'],
        ]);

        // Repository fetches actual entities in batch
        $this->accountRepository->findByIds([1, 2])->willReturn([
            $account1->reveal(),
            $account2->reveal(),
        ]);

        $this->accountSerializer->serialize($account1, 'en', Argument::cetera())->willReturn([
            'id' => 1,
            'name' => 'Account 1',
        ]);

        $this->accountSerializer->serialize($account2, 'en', Argument::cetera())->willReturn([
            'id' => 2,
            'name' => 'Account 2',
        ]);

        $result = $this->accountResolver->resolve([], [], ['locale' => 'en'], 10, 1, 5);

        // hasNextPage is true only when count >= pageSize
        $this->assertFalse($result->getHasNextPage());
        $this->assertSame(
            [
                [
                    'id' => 1,
                    'name' => 'Account 1',
                ],
                [
                    'id' => 2,
                    'name' => 'Account 2',
                ],
            ],
            $result->getItems()
        );
    }

    public function testResolveEmptyResult(): void
    {
        $this->accountSmartContentProvider->findFlatBy(
            Argument::type('array'),
            Argument::type('array'),
            ['locale' => 'en']
        )->willReturn([]);

        $result = $this->accountResolver->resolve([], [], ['locale' => 'en'], 10, 1, 5);

        $this->assertFalse($result->getHasNextPage());
        $this->assertSame([], $result->getItems());
    }
}
