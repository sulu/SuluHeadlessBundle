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

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\LinkResolver;
use Sulu\Bundle\MarkupBundle\Markup\Link\LinkItem;
use Sulu\Bundle\MarkupBundle\Markup\Link\LinkProviderInterface;
use Sulu\Bundle\MarkupBundle\Markup\Link\LinkProviderPoolInterface;
use Sulu\Component\Content\Compat\PropertyInterface;
use Sulu\Component\Content\Compat\StructureInterface;

class LinkResolverTest extends TestCase
{
    public function testGetContentType(): void
    {
        $linkProviderPool = $this->prophesize(LinkProviderPoolInterface::class);
        $linkResolver = new LinkResolver($linkProviderPool->reveal());

        self::assertSame('link', $linkResolver::getContentType());
    }

    public function testResolve(): void
    {
        $providerPool = $this->prophesize(LinkProviderPoolInterface::class);
        $provider = $this->prophesize(LinkProviderInterface::class);
        $linkResolver = new LinkResolver($providerPool->reveal());

        $structure = $this->prophesize(StructureInterface::class);
        $structure->getLanguageCode()
            ->shouldBeCalled()
            ->willReturn('en');

        $property = $this->prophesize(PropertyInterface::class);
        $property->getStructure()
            ->shouldBeCalled()
            ->wilLReturn($structure->reveal());
        $property->getValue()
            ->shouldBeCalled()
            ->willReturn([
                'provider' => 'page',
                'target' => '_self',
                'anchor' => 'link',
                'href' => '76fcf58e-0624-4cf0-85a5-170de9f14252',
                'title' => 'Internal Link',
                'locale' => 'en',
            ]);

        $providerPool->getProvider(Argument::type('string'))
            ->shouldBeCalled()
            ->willReturn($provider->reveal());

        $linkItem = new LinkItem(
            '76fcf58e-0624-4cf0-85a5-170de9f14252',
            'Internal Link',
            'https://example.lo/link',
            true
        );

        $provider->preload(['76fcf58e-0624-4cf0-85a5-170de9f14252'], 'en')
            ->shouldBeCalled()
            ->willReturn([$linkItem]);

        $result = $linkResolver->resolve([], $property->reveal(), 'en');

        $this->assertSame('https://example.lo/link#link', $result->getContent());
        $this->assertSame([
            'provider' => 'page',
            'locale' => 'en',
            'target' => '_self',
            'title' => 'Internal Link',
        ], $result->getView());
    }

    public function testResolveMinimal(): void
    {
        $providerPool = $this->prophesize(LinkProviderPoolInterface::class);
        $provider = $this->prophesize(LinkProviderInterface::class);
        $linkResolver = new LinkResolver($providerPool->reveal());

        $structure = $this->prophesize(StructureInterface::class);
        $structure->getLanguageCode()
            ->shouldBeCalled()
            ->willReturn('en');

        $property = $this->prophesize(PropertyInterface::class);
        $property->getStructure()
            ->shouldBeCalled()
            ->wilLReturn($structure->reveal());
        $property->getValue()
            ->shouldBeCalled()
            ->willReturn([
                'provider' => 'page',
                'href' => '76fcf58e-0624-4cf0-85a5-170de9f14252',
                'locale' => 'en',
            ]);

        $providerPool->getProvider(Argument::type('string'))
            ->shouldBeCalled()
            ->willReturn($provider->reveal());

        $linkItem = new LinkItem(
            '76fcf58e-0624-4cf0-85a5-170de9f14252',
            'Internal Link',
            'https://example.lo/link',
            true
        );

        $provider->preload(['76fcf58e-0624-4cf0-85a5-170de9f14252'], 'en')
            ->shouldBeCalled()
            ->willReturn([$linkItem]);

        $result = $linkResolver->resolve([], $property->reveal(), 'en');

        $this->assertSame('https://example.lo/link', $result->getContent());
        $this->assertSame([
            'provider' => 'page',
            'locale' => 'en',
        ], $result->getView());
    }
}
