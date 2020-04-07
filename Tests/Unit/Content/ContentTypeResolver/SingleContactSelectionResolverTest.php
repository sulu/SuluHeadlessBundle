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
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\SingleContactSelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\ContactSerializerInterface;
use Sulu\Component\Content\Compat\PropertyInterface;

class SingleContactSelectionResolverTest extends TestCase
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
     * @var SingleContactSelectionResolver
     */
    private $singleContactSelectionResolver;

    protected function setUp(): void
    {
        $this->contactManager = $this->prophesize(ContactManager::class);
        $this->contactSerializer = $this->prophesize(ContactSerializerInterface::class);

        $this->singleContactSelectionResolver = new SingleContactSelectionResolver(
            $this->contactManager->reveal(),
            $this->contactSerializer->reveal()
        );
    }

    public function testGetContentType(): void
    {
        self::assertSame('single_contact_selection', $this->singleContactSelectionResolver::getContentType());
    }

    public function testResolve(): void
    {
        $locale = 'en';

        $contact = $this->prophesize(Contact::class);

        $data = 2;

        $this->contactManager->getById(2, $locale)->willReturn($contact->reveal());
        $this->contactSerializer->serialize($contact, $locale, Argument::type(SerializationContext::class))->willReturn([
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
        ]);

        $property = $this->prophesize(PropertyInterface::class);
        $result = $this->singleContactSelectionResolver->resolve($data, $property->reveal(), $locale);

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame(
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
            $result->getContent()
        );

        $this->assertSame(
            [2],
            $result->getView()
        );
    }

    public function testResolveDataIsNull(): void
    {
        $locale = 'en';
        $property = $this->prophesize(PropertyInterface::class);

        $result = $this->singleContactSelectionResolver->resolve(null, $property->reveal(), $locale);

        $this->assertSame([], $result->getContent());

        $this->assertSame([], $result->getView());
    }
}
