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
use Sulu\Bundle\ContactBundle\Entity\ContactInterface;
use Sulu\Bundle\ContactBundle\Entity\ContactRepositoryInterface;
use Sulu\Bundle\HeadlessBundle\Content\DataProviderResolver\ContactDataProviderResolver;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\ContactSerializerInterface;

class ContactDataProviderResolverTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var ObjectProphecy<SmartContentProviderInterface>
     */
    private ObjectProphecy $contactSmartContentProvider;

    /**
     * @var ObjectProphecy<ContactSerializerInterface>
     */
    private ObjectProphecy $contactSerializer;

    /**
     * @var ObjectProphecy<ContactRepositoryInterface>
     */
    private ObjectProphecy $contactRepository;

    private ContactDataProviderResolver $contactResolver;

    protected function setUp(): void
    {
        $this->contactSmartContentProvider = $this->prophesize(SmartContentProviderInterface::class);
        $this->contactSerializer = $this->prophesize(ContactSerializerInterface::class);
        $this->contactRepository = $this->prophesize(ContactRepositoryInterface::class);

        $this->contactResolver = new ContactDataProviderResolver(
            $this->contactSmartContentProvider->reveal(),
            $this->contactSerializer->reveal(),
            $this->contactRepository->reveal(),
        );
    }

    public function testGetDataProvider(): void
    {
        self::assertSame('contacts', $this->contactResolver::getDataProvider());
    }

    public function testGetProviderConfiguration(): void
    {
        $configuration = $this->prophesize(ProviderConfigurationInterface::class);
        $this->contactSmartContentProvider->getConfiguration()->willReturn($configuration->reveal());

        $this->assertSame($configuration->reveal(), $this->contactResolver->getProviderConfiguration());
    }

    public function testGetProviderDefaultParams(): void
    {
        $this->assertSame([], $this->contactResolver->getProviderDefaultParams());
    }

    public function testResolve(): void
    {
        $contact1 = $this->prophesize(ContactInterface::class);
        $contact1->getId()->willReturn(1);
        $contact2 = $this->prophesize(ContactInterface::class);
        $contact2->getId()->willReturn(2);

        // SmartContentProvider returns flat results with id/title
        $this->contactSmartContentProvider->findFlatBy(
            Argument::type('array'),
            Argument::type('array'),
            ['locale' => 'en']
        )->willReturn([
            ['id' => '1', 'title' => 'Contact 1'],
            ['id' => '2', 'title' => 'Contact 2'],
        ]);

        // Repository fetches actual entities in batch
        $this->contactRepository->findByIds([1, 2])->willReturn([
            $contact1->reveal(),
            $contact2->reveal(),
        ]);

        $this->contactSerializer->serialize($contact1, 'en', Argument::cetera())->willReturn([
            'id' => 1,
            'fullName' => 'Contact 1',
        ]);

        $this->contactSerializer->serialize($contact2, 'en', Argument::cetera())->willReturn([
            'id' => 2,
            'fullName' => 'Contact 2',
        ]);

        $result = $this->contactResolver->resolve([], [], ['locale' => 'en'], 10, 1, 5);

        // hasNextPage is true only when count >= pageSize
        $this->assertFalse($result->getHasNextPage());
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

    public function testResolveEmptyResult(): void
    {
        $this->contactSmartContentProvider->findFlatBy(
            Argument::type('array'),
            Argument::type('array'),
            ['locale' => 'en']
        )->willReturn([]);

        $result = $this->contactResolver->resolve([], [], ['locale' => 'en'], 10, 1, 5);

        $this->assertFalse($result->getHasNextPage());
        $this->assertSame([], $result->getItems());
    }
}
