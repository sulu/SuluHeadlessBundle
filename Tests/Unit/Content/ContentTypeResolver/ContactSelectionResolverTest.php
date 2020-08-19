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
use Sulu\Bundle\ContactBundle\Api\Contact;
use Sulu\Bundle\ContactBundle\Contact\ContactManager;
use Sulu\Bundle\ContactBundle\Entity\ContactInterface;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\ContactSelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\ContactSerializerInterface;
use Sulu\Component\Content\Compat\PropertyInterface;

class ContactSelectionResolverTest extends TestCase
{
    /**
     * @var ContactManager|ObjectProphecy
     */
    private $contactManager;

    /**
     * @var ContactSerializerInterface|ObjectProphecy
     */
    private $contactSerializer;

    /**
     * @var ContactSelectionResolver
     */
    private $contactSelectionResolver;

    protected function setUp(): void
    {
        $this->contactManager = $this->prophesize(ContactManager::class);
        $this->contactSerializer = $this->prophesize(ContactSerializerInterface::class);

        $this->contactSelectionResolver = new ContactSelectionResolver(
            $this->contactManager->reveal(),
            $this->contactSerializer->reveal()
        );
    }

    public function testGetContentType(): void
    {
        self::assertSame('contact_selection', $this->contactSelectionResolver::getContentType());
    }

    public function testResolveWithOneContact(): void
    {
        $locale = 'en';

        $contact = $this->prophesize(ContactInterface::class);
        $apiContact = $this->prophesize(Contact::class);
        $apiContact->getEntity()->willReturn($contact->reveal());

        $data = [2];

        $this->contactManager->getByIds($data, $locale)->willReturn([$apiContact->reveal()]);
        $this->contactSerializer->serialize($contact, $locale, Argument::type(SerializationContext::class))->willReturn(
            [
                'id' => 2,
                'firstName' => 'John',
                'lastName' => 'Doe',
                'fullName' => 'John Doe',
                'title' => 'fancyTitle',
                'position' => 'CEO',
                'avatar' => [
                    'id' => 2,
                    'formatUri' => '/media/2/{format}/media-2.jpg?v=1-0',
                ],
            ]
        );

        $property = $this->prophesize(PropertyInterface::class);
        $result = $this->contactSelectionResolver->resolve($data, $property->reveal(), $locale);

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame(
            [
                [
                    'id' => 2,
                    'firstName' => 'John',
                    'lastName' => 'Doe',
                    'fullName' => 'John Doe',
                    'title' => 'fancyTitle',
                    'position' => 'CEO',
                    'avatar' => [
                        'id' => 2,
                        'formatUri' => '/media/2/{format}/media-2.jpg?v=1-0',
                    ],
                ],
            ],
            $result->getContent()
        );

        $this->assertSame(
            ['ids' => [2]],
            $result->getView()
        );
    }

    public function testResolveWithManyContacts(): void
    {
        $locale = 'en';

        $contact = $this->prophesize(ContactInterface::class);
        $apiContact = $this->prophesize(Contact::class);
        $apiContact->getEntity()->willReturn($contact->reveal());

        $data = [2, 3, 4];

        $this->contactManager->getByIds($data, $locale)->willReturn([$apiContact->reveal(), $apiContact->reveal(), $apiContact->reveal()]);
        $this->contactSerializer->serialize($contact, $locale, Argument::type(SerializationContext::class))->willReturn(
            [
                'id' => 2,
                'firstName' => 'John',
                'lastName' => 'Doe',
                'fullName' => 'John Doe',
                'title' => 'fancyTitle',
                'position' => 'CEO',
                'avatar' => [
                    'id' => 2,
                    'formatUri' => '/media/2/{format}/media-2.jpg?v=1-0',
                ],
            ],
            [
                'id' => 3,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'fullName' => 'Max Mustermann',
                'title' => 'fancyTitle',
                'position' => 'CTO',
                'avatar' => [
                    'id' => 3,
                    'formatUri' => '/media/3/{format}/media-3.jpg?v=1-0',
                ],
            ],
            [
                'id' => 4,
                'firstName' => 'Diana',
                'lastName' => 'Doe',
                'fullName' => 'Diana Doe',
                'title' => 'fancyTitle',
                'position' => 'CFO',
                'avatar' => [
                    'id' => 4,
                    'formatUri' => '/media/4/{format}/media-2.jpg?v=1-0',
                ],
            ]
        );

        $property = $this->prophesize(PropertyInterface::class);
        $result = $this->contactSelectionResolver->resolve($data, $property->reveal(), $locale);

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame(
            [
                [
                    'id' => 2,
                    'firstName' => 'John',
                    'lastName' => 'Doe',
                    'fullName' => 'John Doe',
                    'title' => 'fancyTitle',
                    'position' => 'CEO',
                    'avatar' => [
                        'id' => 2,
                        'formatUri' => '/media/2/{format}/media-2.jpg?v=1-0',
                    ],
                ],
                [
                    'id' => 3,
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'fullName' => 'Max Mustermann',
                    'title' => 'fancyTitle',
                    'position' => 'CTO',
                    'avatar' => [
                        'id' => 3,
                        'formatUri' => '/media/3/{format}/media-3.jpg?v=1-0',
                    ],
                ],
                [
                    'id' => 4,
                    'firstName' => 'Diana',
                    'lastName' => 'Doe',
                    'fullName' => 'Diana Doe',
                    'title' => 'fancyTitle',
                    'position' => 'CFO',
                    'avatar' => [
                        'id' => 4,
                        'formatUri' => '/media/4/{format}/media-2.jpg?v=1-0',
                    ],
                ],
            ],
            $result->getContent()
        );

        $this->assertSame(
            ['ids' => [2, 3, 4]],
            $result->getView()
        );
    }

    public function testResolveDataIsNull(): void
    {
        $locale = 'en';
        $property = $this->prophesize(PropertyInterface::class);

        $result = $this->contactSelectionResolver->resolve(null, $property->reveal(), $locale);

        $this->assertNull($result->getContent());

        $this->assertSame([], $result->getView());
    }
}
