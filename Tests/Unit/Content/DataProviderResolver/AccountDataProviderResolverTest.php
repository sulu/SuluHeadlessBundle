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
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\ContactBundle\Api\Account;
use Sulu\Bundle\ContactBundle\Entity\AccountInterface;
use Sulu\Bundle\HeadlessBundle\Content\DataProviderResolver\AccountDataProviderResolver;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\AccountSerializerInterface;
use Sulu\Component\Contact\SmartContent\AccountDataProvider;
use Sulu\Component\Content\Compat\PropertyParameter;
use Sulu\Component\SmartContent\Configuration\ProviderConfigurationInterface;
use Sulu\Component\SmartContent\DataProviderResult;
use Sulu\Component\SmartContent\ResourceItemInterface;

class AccountDataProviderResolverTest extends TestCase
{
    /**
     * @var AccountDataProvider|ObjectProphecy
     */
    private $accountDataProvider;

    /**
     * @var AccountSerializerInterface|ObjectProphecy
     */
    private $accountSerializer;

    /**
     * @var AccountDataProviderResolver
     */
    private $accountResolver;

    protected function setUp(): void
    {
        $this->accountDataProvider = $this->prophesize(AccountDataProvider::class);
        $this->accountSerializer = $this->prophesize(AccountSerializerInterface::class);

        $this->accountResolver = new AccountDataProviderResolver(
            $this->accountDataProvider->reveal(),
            $this->accountSerializer->reveal()
        );
    }

    public function testGetDataProvider(): void
    {
        self::assertSame('accounts', $this->accountResolver::getDataProvider());
    }

    public function testGetProviderConfiguration(): void
    {
        $configuration = $this->prophesize(ProviderConfigurationInterface::class);
        $this->accountDataProvider->getConfiguration()->willReturn($configuration->reveal());

        $this->assertSame($configuration->reveal(), $this->accountResolver->getProviderConfiguration());
    }

    public function testGetProviderDefaultParams(): void
    {
        $propertyParameter = $this->prophesize(PropertyParameter::class);
        $this->accountDataProvider->getDefaultPropertyParameter()->willReturn(['test' => $propertyParameter->reveal()]);

        $this->assertSame(['test' => $propertyParameter->reveal()], $this->accountResolver->getProviderDefaultParams());
    }

    public function testResolve(): void
    {
        $account1 = $this->prophesize(AccountInterface::class);
        $apiAccount1 = $this->prophesize(Account::class);
        $apiAccount1->getEntity()->willReturn($account1->reveal());

        $account2 = $this->prophesize(AccountInterface::class);
        $apiAccount2 = $this->prophesize(Account::class);
        $apiAccount2->getEntity()->willReturn($account2->reveal());

        $resourceItem1 = $this->prophesize(ResourceItemInterface::class);
        $resourceItem1->getResource()->willReturn($apiAccount1->reveal());

        $resourceItem2 = $this->prophesize(ResourceItemInterface::class);
        $resourceItem2->getResource()->willReturn($apiAccount2->reveal());

        $providerResult = $this->prophesize(DataProviderResult::class);
        $providerResult->getHasNextPage()->willReturn(true);
        $providerResult->getItems()->willReturn([$resourceItem1, $resourceItem2]);

        $this->accountDataProvider->resolveResourceItems([], [], ['locale' => 'en'], 10, 1, 5)->willReturn($providerResult->reveal());

        $this->accountSerializer->serialize($account1, 'en', Argument::cetera())->willReturn([
            'id' => 1,
            'name' => 'Account 1',
        ]);

        $this->accountSerializer->serialize($account2, 'en', Argument::cetera())->willReturn([
            'id' => 2,
            'name' => 'Account 2',
        ]);

        $result = $this->accountResolver->resolve([], [], ['locale' => 'en'], 10, 1, 5);

        $this->assertTrue($result->getHasNextPage());
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
}
