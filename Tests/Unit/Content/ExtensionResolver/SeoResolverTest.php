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
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderInterface;
use Sulu\Bundle\HeadlessBundle\Content\ContentResolverInterface;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\ExtensionResolver\SeoResolver;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Domain\Model\SeoInterface;

class SeoResolverTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var ObjectProphecy<MetadataProviderInterface>
     */
    private ObjectProphecy $formMetadataProvider;

    /**
     * @var ObjectProphecy<ContentResolverInterface>
     */
    private ObjectProphecy $contentResolver;

    private SeoResolver $seoResolver;

    protected function setUp(): void
    {
        $this->formMetadataProvider = $this->prophesize(MetadataProviderInterface::class);
        $this->contentResolver = $this->prophesize(ContentResolverInterface::class);

        $this->seoResolver = new SeoResolver(
            $this->formMetadataProvider->reveal(),
            $this->contentResolver->reveal(),
        );
    }

    public function testGetPrefix(): void
    {
        $this->assertSame('seo.', $this->seoResolver->getPrefix());
    }

    public function testResolveNotSeoInterface(): void
    {
        $dimensionContent = $this->prophesize(DimensionContentInterface::class);

        $result = $this->seoResolver->resolve(
            $dimensionContent->reveal(),
            ['myTitle' => 'seo.title'],
            'en'
        );

        $this->assertSame([], $result->getContent());
        $this->assertSame([], $result->getView());
    }

    public function testResolveEmptyProperties(): void
    {
        $dimensionContent = $this->createSeoDimensionContent();
        $dimensionContent->getSeoData()->willReturn(['title' => 'SEO Title']);
        $dimensionContent->getSeoNoIndex()->willReturn(false);
        $dimensionContent->getSeoNoFollow()->willReturn(false);
        $dimensionContent->getSeoHideInSitemap()->willReturn(false);

        $result = $this->seoResolver->resolve(
            $dimensionContent->reveal(),
            ['title' => 'title'],
            'en'
        );

        $this->assertSame([], $result->getContent());
        $this->assertSame([], $result->getView());
    }

    public function testResolveWithSeoData(): void
    {
        $dimensionContent = $this->createSeoDimensionContent();
        $dimensionContent->getSeoData()->willReturn(['title' => 'SEO Title']);
        $dimensionContent->getSeoNoIndex()->willReturn(false);
        $dimensionContent->getSeoNoFollow()->willReturn(true);
        $dimensionContent->getSeoHideInSitemap()->willReturn(false);

        $titleField = new FieldMetadata('seo/title');
        $titleField->setType('text_line');

        $formMetadata = $this->prophesize(FormMetadata::class);
        $formMetadata->getFlatFieldMetadata()->willReturn(['seo/title' => $titleField]);

        $this->formMetadataProvider->getMetadata('content_seo', 'en', Argument::type('array'))
            ->willReturn($formMetadata->reveal());

        $this->contentResolver->resolve('SEO Title', $titleField, 'en', Argument::type('array'))
            ->willReturn(new ContentView('SEO Title', []));

        $result = $this->seoResolver->resolve(
            $dimensionContent->reveal(),
            ['myTitle' => 'seo.title'],
            'en'
        );

        $this->assertSame(['myTitle' => 'SEO Title'], $result->getContent());
        $this->assertSame(['myTitle' => []], $result->getView());
    }

    public function testResolveWithBooleanFields(): void
    {
        $dimensionContent = $this->createSeoDimensionContent();
        $dimensionContent->getSeoData()->willReturn([]);
        $dimensionContent->getSeoNoIndex()->willReturn(true);
        $dimensionContent->getSeoNoFollow()->willReturn(false);
        $dimensionContent->getSeoHideInSitemap()->willReturn(true);

        $formMetadata = $this->prophesize(FormMetadata::class);
        $formMetadata->getFlatFieldMetadata()->willReturn([]);

        $this->formMetadataProvider->getMetadata('content_seo', 'en', Argument::type('array'))
            ->willReturn($formMetadata->reveal());

        $result = $this->seoResolver->resolve(
            $dimensionContent->reveal(),
            [
                'noIndex' => 'seo.noIndex',
                'noFollow' => 'seo.noFollow',
                'hideInSitemap' => 'seo.hideInSitemap',
            ],
            'en'
        );

        $this->assertSame(
            [
                'noIndex' => true,
                'noFollow' => false,
                'hideInSitemap' => true,
            ],
            $result->getContent()
        );
    }

    public function testResolveWithNullContent(): void
    {
        $dimensionContent = $this->createSeoDimensionContent();
        $dimensionContent->getSeoData()->willReturn([]);
        $dimensionContent->getSeoNoIndex()->willReturn(false);
        $dimensionContent->getSeoNoFollow()->willReturn(false);
        $dimensionContent->getSeoHideInSitemap()->willReturn(false);

        $descField = new FieldMetadata('seo/description');
        $descField->setType('text_area');

        $formMetadata = $this->prophesize(FormMetadata::class);
        $formMetadata->getFlatFieldMetadata()->willReturn(['seo/description' => $descField]);

        $this->formMetadataProvider->getMetadata('content_seo', 'en', Argument::type('array'))
            ->willReturn($formMetadata->reveal());

        $this->contentResolver->resolve(null, $descField, 'en', Argument::type('array'))
            ->willReturn(new ContentView(null, []));

        $result = $this->seoResolver->resolve(
            $dimensionContent->reveal(),
            ['myDesc' => 'seo.description'],
            'en'
        );

        $this->assertSame(['myDesc' => ''], $result->getContent());
    }

    public function testResolveWithNullContentArrayType(): void
    {
        $dimensionContent = $this->createSeoDimensionContent();
        $dimensionContent->getSeoData()->willReturn([]);
        $dimensionContent->getSeoNoIndex()->willReturn(false);
        $dimensionContent->getSeoNoFollow()->willReturn(false);
        $dimensionContent->getSeoHideInSitemap()->willReturn(false);

        $mediaField = new FieldMetadata('seo/media');
        $mediaField->setType('single_media_selection');

        $formMetadata = $this->prophesize(FormMetadata::class);
        $formMetadata->getFlatFieldMetadata()->willReturn(['seo/media' => $mediaField]);

        $this->formMetadataProvider->getMetadata('content_seo', 'en', Argument::type('array'))
            ->willReturn($formMetadata->reveal());

        $this->contentResolver->resolve(null, $mediaField, 'en', Argument::type('array'))
            ->willReturn(new ContentView(null, []));

        $result = $this->seoResolver->resolve(
            $dimensionContent->reveal(),
            ['myMedia' => 'seo.media'],
            'en'
        );

        $this->assertSame(['myMedia' => []], $result->getContent());
    }

    public function testResolveSkipsSearchResultType(): void
    {
        $dimensionContent = $this->createSeoDimensionContent();
        $dimensionContent->getSeoData()->willReturn(['title' => 'SEO Title']);
        $dimensionContent->getSeoNoIndex()->willReturn(false);
        $dimensionContent->getSeoNoFollow()->willReturn(false);
        $dimensionContent->getSeoHideInSitemap()->willReturn(false);

        $searchResultField = new FieldMetadata('seo/searchResult');
        $searchResultField->setType('search_result');

        $formMetadata = $this->prophesize(FormMetadata::class);
        $formMetadata->getFlatFieldMetadata()->willReturn(['seo/searchResult' => $searchResultField]);

        $this->formMetadataProvider->getMetadata('content_seo', 'en', Argument::type('array'))
            ->willReturn($formMetadata->reveal());

        $result = $this->seoResolver->resolve(
            $dimensionContent->reveal(),
            ['searchResult' => 'seo.searchResult'],
            'en'
        );

        $this->assertSame([], $result->getContent());
    }

    public function testResolveFieldNotInMetadata(): void
    {
        $dimensionContent = $this->createSeoDimensionContent();
        $dimensionContent->getSeoData()->willReturn(['title' => 'SEO Title']);
        $dimensionContent->getSeoNoIndex()->willReturn(false);
        $dimensionContent->getSeoNoFollow()->willReturn(false);
        $dimensionContent->getSeoHideInSitemap()->willReturn(false);

        $formMetadata = $this->prophesize(FormMetadata::class);
        $formMetadata->getFlatFieldMetadata()->willReturn([]);

        $this->formMetadataProvider->getMetadata('content_seo', 'en', Argument::type('array'))
            ->willReturn($formMetadata->reveal());

        $result = $this->seoResolver->resolve(
            $dimensionContent->reveal(),
            ['myField' => 'seo.unknown'],
            'en'
        );

        $this->assertSame([], $result->getContent());
    }

    public function testHasSeoPropertiesTrue(): void
    {
        $properties = [
            'title' => 'title',
            'mySeoTitle' => 'seo.title',
        ];

        $this->assertTrue($this->seoResolver->hasSeoProperties($properties));
    }

    public function testHasSeoPropertiesFalse(): void
    {
        $properties = [
            'title' => 'title',
            'excerptTitle' => 'excerpt.title',
        ];

        $this->assertFalse($this->seoResolver->hasSeoProperties($properties));
    }

    private function createSeoDimensionContent(): ObjectProphecy
    {
        return $this->prophesize(DimensionContentInterface::class)
            ->willImplement(SeoInterface::class);
    }
}
