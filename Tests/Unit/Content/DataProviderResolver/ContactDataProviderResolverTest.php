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
use Sulu\Bundle\ContactBundle\Api\Contact;
use Sulu\Bundle\ContactBundle\Entity\ContactInterface;
use Sulu\Bundle\HeadlessBundle\Content\DataProviderResolver\ContactDataProviderResolver;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\ContactSerializerInterface;
use Sulu\Component\Contact\SmartContent\ContactDataProvider;
use Sulu\Component\Content\Compat\PropertyParameter;
use Sulu\Component\SmartContent\Configuration\ProviderConfigurationInterface;
use Sulu\Component\SmartContent\DataProviderResult;
use Sulu\Component\SmartContent\ResourceItemInterface;

class ContactDataProviderResolverTest extends TestCase
{
    /**
     * @var ContactDataProvider|ObjectProphecy
     */
    private $contactDataProvider;

    /**
     * @var ContactSerializerInterface|ObjectProphecy
     */
    private $contactSerializer;

    /**
     * @var ContactDataProviderResolver
     */
    private $contactResolver;

    protected function setUp(): void
    {
        $this->contactDataProvider = $this->prophesize(ContactDataProvider::class);
        $this->contactSerializer = $this->prophesize(ContactSerializerInterface::class);

        $this->contactResolver = new ContactDataProviderResolver(
            $this->contactDataProvider->reveal(),
            $this->contactSerializer->reveal()
        );
    }

    public function testGetDataProvider(): void
    {
        self::assertSame('contacts', $this->contactResolver::getDataProvider());
    }

    public function testGetProviderConfiguration(): void
    {
        $configuration = $this->prophesize(ProviderConfigurationInterface::class);
        $this->contactDataProvider->getConfiguration()->willReturn($configuration->reveal());

        $this->assertSame($configuration->reveal(), $this->contactResolver->getProviderConfiguration());
    }

    public function testGetProviderDefaultParams(): void
    {
        $propertyParameter = $this->prophesize(PropertyParameter::class);
        $this->contactDataProvider->getDefaultPropertyParameter()->willReturn(['test' => $propertyParameter->reveal()]);

        $this->assertSame(['test' => $propertyParameter->reveal()], $this->contactResolver->getProviderDefaultParams());
    }

    public function testResolve(): void
    {
        $contact1 = $this->prophesize(ContactInterface::class);
        $apiContact1 = $this->prophesize(Contact::class);
        $apiContact1->getEntity()->willReturn($contact1->reveal());

        $contact2 = $this->prophesize(ContactInterface::class);
        $apiContact2 = $this->prophesize(Contact::class);
        $apiContact2->getEntity()->willReturn($contact2->reveal());

        $resourceItem1 = $this->prophesize(ResourceItemInterface::class);
        $resourceItem1->getResource()->willReturn($apiContact1->reveal());

        $resourceItem2 = $this->prophesize(ResourceItemInterface::class);
        $resourceItem2->getResource()->willReturn($apiContact2->reveal());

        $providerResult = $this->prophesize(DataProviderResult::class);
        $providerResult->getHasNextPage()->willReturn(true);
        $providerResult->getItems()->willReturn([$resourceItem1, $resourceItem2]);

        $this->contactDataProvider->resolveResourceItems([], [], ['locale' => 'en'], 10, 1, 5)->willReturn($providerResult->reveal());

        $this->contactSerializer->serialize($contact1, 'en', Argument::cetera())->willReturn([
            'id' => 1,
            'fullName' => 'Contact 1',
        ]);

        $this->contactSerializer->serialize($contact2, 'en', Argument::cetera())->willReturn([
            'id' => 2,
            'fullName' => 'Contact 2',
        ]);

        $result = $this->contactResolver->resolve([], [], ['locale' => 'en'], 10, 1, 5);

        $this->assertTrue($result->getHasNextPage());
        $this->assertSame(
            [
                [
                    'id' => 1,
                    'fullName' => 'Contact 1',
                ],
                [
                    'id' => 2,
                    'fullName' => 'Contact 2',
                ],
            ],
            $result->getItems()
        );
    }
}
