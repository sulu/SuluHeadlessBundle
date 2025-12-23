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
use Prophecy\PhpUnit\ProphecyTrait;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\LinkResolver;
use Sulu\Bundle\MarkupBundle\Markup\Link\LinkItem;
use Sulu\Bundle\MarkupBundle\Markup\Link\LinkProviderInterface;
use Sulu\Bundle\MarkupBundle\Markup\Link\LinkProviderPoolInterface;

class LinkResolverTest extends TestCase
{
    use ProphecyTrait;

    private FieldMetadata $fieldMetadata;

    protected function setUp(): void
    {
        $this->fieldMetadata = new FieldMetadata('link');
    }

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

        $result = $linkResolver->resolve([
            'provider' => 'page',
            'target' => '_self',
            'anchor' => 'link',
            'href' => '76fcf58e-0624-4cf0-85a5-170de9f14252',
            'title' => 'Internal Link',
            'locale' => 'en',
        ], $this->fieldMetadata, 'en');

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

        $result = $linkResolver->resolve([
            'provider' => 'page',
            'href' => '76fcf58e-0624-4cf0-85a5-170de9f14252',
            'locale' => 'en',
        ], $this->fieldMetadata, 'en');

        $this->assertSame('https://example.lo/link', $result->getContent());
        $this->assertSame([
            'provider' => 'page',
            'locale' => 'en',
        ], $result->getView());
    }

    public function testResolveWithEmptyData(): void
    {
        $providerPool = $this->prophesize(LinkProviderPoolInterface::class);
        $linkResolver = new LinkResolver($providerPool->reveal());

        $result = $linkResolver->resolve([], $this->fieldMetadata, 'en');

        $this->assertNull($result->getContent());
        $this->assertSame([], $result->getView());
    }

    public function testResolveWithNullData(): void
    {
        $providerPool = $this->prophesize(LinkProviderPoolInterface::class);
        $linkResolver = new LinkResolver($providerPool->reveal());

        $result = $linkResolver->resolve(null, $this->fieldMetadata, 'en');

        $this->assertNull($result->getContent());
        $this->assertSame([], $result->getView());
    }

    public function testResolveWithMissingHref(): void
    {
        $providerPool = $this->prophesize(LinkProviderPoolInterface::class);
        $linkResolver = new LinkResolver($providerPool->reveal());

        $result = $linkResolver->resolve([
            'provider' => 'page',
            'locale' => 'en',
        ], $this->fieldMetadata, 'en');

        $this->assertNull($result->getContent());
        $this->assertSame([
            'provider' => 'page',
            'locale' => 'en',
        ], $result->getView());
    }

    public function testResolveWithEmptyLinkItems(): void
    {
        $providerPool = $this->prophesize(LinkProviderPoolInterface::class);
        $provider = $this->prophesize(LinkProviderInterface::class);
        $linkResolver = new LinkResolver($providerPool->reveal());

        $providerPool->getProvider('page')
            ->shouldBeCalled()
            ->willReturn($provider->reveal());

        $provider->preload(['non-existent-id'], 'en')
            ->shouldBeCalled()
            ->willReturn([]);

        $result = $linkResolver->resolve([
            'provider' => 'page',
            'href' => 'non-existent-id',
            'locale' => 'en',
        ], $this->fieldMetadata, 'en');

        $this->assertNull($result->getContent());
    }
}
