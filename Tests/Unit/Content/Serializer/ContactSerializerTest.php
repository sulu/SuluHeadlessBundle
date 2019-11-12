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
use Sulu\Bundle\ContactBundle\Api\Contact;
use Sulu\Bundle\ContactBundle\Entity\ContactTitle;
use Sulu\Bundle\ContactBundle\Entity\ContactTitleRepository;
use Sulu\Bundle\ContactBundle\Entity\Position;
use Sulu\Bundle\ContactBundle\Entity\PositionRepository;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\ContactSerializer;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\MediaSerializer;
use Sulu\Bundle\MediaBundle\Api\Media;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Sulu\Component\Serializer\ArraySerializerInterface;

class ContactSerializerTest extends TestCase
{
    /**
     * @var ContactSerializer
     */
    private $contactSerializer;

    /**
     * @var ArraySerializerInterface|ObjectProphecy
     */
    private $arraySerializer;

    /**
     * @var MediaManagerInterface|ObjectProphecy
     */
    private $mediaManager;

    /**
     * @var MediaSerializer|ObjectProphecy
     */
    private $mediaSerializer;

    /**
     * @var ContactTitleRepository|ObjectProphecy
     */
    private $contactTitleRepository;

    /**
     * @var PositionRepository|ObjectProphecy
     */
    private $positionRepository;

    protected function setUp(): void
    {
        $this->arraySerializer = $this->prophesize(ArraySerializerInterface::class);
        $this->mediaManager = $this->prophesize(MediaManagerInterface::class);
        $this->mediaSerializer = $this->prophesize(MediaSerializer::class);
        $this->contactTitleRepository = $this->prophesize(ContactTitleRepository::class);
        $this->positionRepository = $this->prophesize(PositionRepository::class);

        $this->contactSerializer = new ContactSerializer(
            $this->arraySerializer->reveal(),
            $this->mediaManager->reveal(),
            $this->mediaSerializer->reveal(),
            $this->contactTitleRepository->reveal(),
            $this->positionRepository->reveal()
        );
    }

    public function testSerialize(): void
    {
        $locale = 'en';
        $contact = $this->prophesize(Contact::class);
        $contact->getAvatar()->willReturn([
            'id' => 2,
            'url' => '/media/2/download/sulu.png?v=1',
        ]);

        $media = $this->prophesize(Media::class);
        $media->getId()->willReturn(1);
        $media->getName()->willReturn('media-1.png');
        $media->getMimeType()->willReturn('image/png');
        $media->getVersion()->willReturn(1);
        $media->getSubVersion()->willReturn(0);

        $this->arraySerializer->serialize($contact, null)->willReturn([
            'id' => 1,
            'firstName' => 'John',
            'lastName' => 'Doe',
            'fullName' => 'John Doe',
            'title' => 1,
            'position' => 1,
            'avatar' => [
                'id' => 2,
                'url' => '/media/2/download/sulu.png?v=1',
            ],
        ]);

        $this->mediaSerializer->serialize($media)->willReturn([
            'id' => 2,
            'formatUri' => '/media/2/{format}/media-2.jpg?v=1-0',
        ]);

        $this->mediaManager->getById(Argument::any(), $locale)->shouldBeCalled()->willReturn($media->reveal());

        $contactTitle = $this->prophesize(ContactTitle::class);
        $contactTitle->getTitle()->willReturn('fancyTitle');
        $this->contactTitleRepository->find(Argument::any())->willReturn($contactTitle->reveal());

        $contactPosition = $this->prophesize(Position::class);
        $contactPosition->getPosition()->willReturn('CEO');
        $this->positionRepository->find(Argument::any())->wilLReturn($contactPosition->reveal());

        $result = $this->contactSerializer->serialize($contact->reveal(), $locale, null);

        $this->assertSame([
            'id' => 1,
            'firstName' => 'John',
            'lastName' => 'Doe',
            'fullName' => 'John Doe',
            'title' => 'fancyTitle',
            'position' => 'CEO',
            'avatar' => [
                'id' => 2,
                'formatUri' => '/media/2/{format}/media-2.jpg?v=1-0',
            ],
        ], $result);
    }

    public function testSerializeWithContext(): void
    {
        $locale = 'en';
        $contact = $this->prophesize(Contact::class);
        $contact->getAvatar()->willReturn([
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

        $this->arraySerializer->serialize($contact, $context)->willReturn([
            'id' => 1,
            'firstName' => 'John',
            'lastName' => 'Doe',
            'fullName' => 'John Doe',
            'title' => 1,
            'position' => 1,
            'avatar' => [
                'id' => 2,
                'url' => '/media/2/download/sulu.png?v=1',
            ],
        ]);

        $this->mediaSerializer->serialize($media)->willReturn([
            'id' => 2,
            'formatUri' => '/media/2/{format}/media-2.jpg?v=1-0',
        ]);

        $this->mediaManager->getById(Argument::any(), $locale)->shouldBeCalled()->willReturn($media->reveal());

        $contactTitle = $this->prophesize(ContactTitle::class);
        $contactTitle->getTitle()->willReturn('fancyTitle');
        $this->contactTitleRepository->find(Argument::any())->willReturn($contactTitle->reveal());

        $contactPosition = $this->prophesize(Position::class);
        $contactPosition->getPosition()->willReturn('CEO');
        $this->positionRepository->find(Argument::any())->wilLReturn($contactPosition->reveal());

        $result = $this->contactSerializer->serialize($contact->reveal(), $locale, $context->reveal());

        $this->assertSame([
            'id' => 1,
            'firstName' => 'John',
            'lastName' => 'Doe',
            'fullName' => 'John Doe',
            'title' => 'fancyTitle',
            'position' => 'CEO',
            'avatar' => [
                'id' => 2,
                'formatUri' => '/media/2/{format}/media-2.jpg?v=1-0',
            ],
        ], $result);
    }

    public function testSerializeWithoutAvatarAndTitleAndPosition(): void
    {
        $locale = 'en';
        $contact = $this->prophesize(Contact::class);
        $contact->getAvatar()->willReturn(null);

        $context = $this->prophesize(SerializationContext::class);

        $this->arraySerializer->serialize($contact, $context)->willReturn([
            'id' => 1,
            'firstName' => 'John',
            'lastName' => 'Doe',
            'fullName' => 'John Doe',
        ]);

        $result = $this->contactSerializer->serialize($contact->reveal(), $locale, $context->reveal());

        $this->assertSame([
            'id' => 1,
            'firstName' => 'John',
            'lastName' => 'Doe',
            'fullName' => 'John Doe',
        ], $result);
    }
}
