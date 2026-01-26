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

namespace Sulu\Bundle\HeadlessBundle\Tests\Unit\Content\ExtensionResolver;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Sulu\Bundle\HeadlessBundle\Content\ExtensionResolver\ExtensionResolverInterface;
use Sulu\Bundle\HeadlessBundle\Content\ExtensionResolver\ExtensionResolverProvider;

class ExtensionResolverProviderTest extends TestCase
{
    use ProphecyTrait;

    public function testGetResolvers(): void
    {
        $resolver1 = $this->prophesize(ExtensionResolverInterface::class);
        $resolver1->getPrefix()->willReturn('excerpt.');

        $resolver2 = $this->prophesize(ExtensionResolverInterface::class);
        $resolver2->getPrefix()->willReturn('seo.');

        $provider = new ExtensionResolverProvider([
            $resolver1->reveal(),
            $resolver2->reveal(),
        ]);

        $resolvers = \iterator_to_array($provider->getResolvers());

        $this->assertCount(2, $resolvers);
    }

    public function testGetResolverByPrefix(): void
    {
        $resolver1 = $this->prophesize(ExtensionResolverInterface::class);
        $resolver1->getPrefix()->willReturn('excerpt.');

        $resolver2 = $this->prophesize(ExtensionResolverInterface::class);
        $resolver2->getPrefix()->willReturn('seo.');

        $provider = new ExtensionResolverProvider([
            $resolver1->reveal(),
            $resolver2->reveal(),
        ]);

        $result = $provider->getResolverByPrefix('seo.');

        $this->assertSame($resolver2->reveal(), $result);
    }

    public function testGetResolverByPrefixNotFound(): void
    {
        $resolver1 = $this->prophesize(ExtensionResolverInterface::class);
        $resolver1->getPrefix()->willReturn('excerpt.');

        $provider = new ExtensionResolverProvider([$resolver1->reveal()]);

        $result = $provider->getResolverByPrefix('unknown.');

        $this->assertNull($result);
    }

    public function testHasPropertiesWithPrefix(): void
    {
        $provider = new ExtensionResolverProvider([]);

        $properties = [
            'title' => 'title',
            'myExcerpt' => 'excerpt.title',
            'description' => 'description',
        ];

        $this->assertTrue($provider->hasPropertiesWithPrefix($properties, 'excerpt.'));
        $this->assertFalse($provider->hasPropertiesWithPrefix($properties, 'seo.'));
    }

    public function testHasPropertiesWithPrefixEmpty(): void
    {
        $provider = new ExtensionResolverProvider([]);

        $this->assertFalse($provider->hasPropertiesWithPrefix([], 'excerpt.'));
    }
}
