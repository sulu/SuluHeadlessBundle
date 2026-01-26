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
use Sulu\Bundle\HeadlessBundle\Content\ExtensionResolver\ExcerptResolver;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Domain\Model\ExcerptInterface;
use Sulu\Content\Domain\Model\TaxonomyInterface;

class ExcerptResolverTest extends TestCase
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

    private ExcerptResolver $excerptResolver;

    protected function setUp(): void
    {
        $this->formMetadataProvider = $this->prophesize(MetadataProviderInterface::class);
        $this->contentResolver = $this->prophesize(ContentResolverInterface::class);

        $this->excerptResolver = new ExcerptResolver(
            $this->formMetadataProvider->reveal(),
            $this->contentResolver->reveal(),
        );
    }

    public function testGetPrefix(): void
    {
        $this->assertSame('excerpt.', $this->excerptResolver->getPrefix());
    }

    public function testResolveNotExcerptInterface(): void
    {
        $dimensionContent = $this->prophesize(DimensionContentInterface::class);

        $result = $this->excerptResolver->resolve(
            $dimensionContent->reveal(),
            ['myTitle' => 'excerpt.title'],
            'en'
        );

        $this->assertSame([], $result->getContent());
        $this->assertSame([], $result->getView());
    }

    public function testResolveEmptyProperties(): void
    {
        $dimensionContent = $this->createExcerptDimensionContent();

        $result = $this->excerptResolver->resolve(
            $dimensionContent->reveal(),
            ['title' => 'title'],
            'en'
        );

        $this->assertSame([], $result->getContent());
        $this->assertSame([], $result->getView());
    }

    public function testResolveWithExcerptData(): void
    {
        $dimensionContent = $this->createExcerptDimensionContent();
        $dimensionContent->getExcerptData()->willReturn(['title' => 'Excerpt Title']);

        $titleField = new FieldMetadata('excerpt/title');
        $titleField->setType('text_line');

        $formMetadata = $this->prophesize(FormMetadata::class);
        $formMetadata->getFlatFieldMetadata()->willReturn(['excerpt/title' => $titleField]);

        $this->formMetadataProvider->getMetadata('content_excerpt', 'en', Argument::type('array'))
            ->willReturn($formMetadata->reveal());

        $this->contentResolver->resolve('Excerpt Title', $titleField, 'en', Argument::type('array'))
            ->willReturn(new ContentView('Excerpt Title', []));

        $result = $this->excerptResolver->resolve(
            $dimensionContent->reveal(),
            ['myTitle' => 'excerpt.title'],
            'en'
        );

        $this->assertSame(['myTitle' => 'Excerpt Title'], $result->getContent());
        $this->assertSame(['myTitle' => []], $result->getView());
    }

    public function testResolveWithTaxonomyData(): void
    {
        $dimensionContent = $this->createTaxonomyDimensionContent();
        $dimensionContent->getExcerptCategoryIds()->willReturn([1, 2, 3]);
        $dimensionContent->getExcerptTagNames()->willReturn(['tag1', 'tag2']);

        $categoriesField = new FieldMetadata('excerptCategories');
        $categoriesField->setType('category_selection');

        $formMetadata = $this->prophesize(FormMetadata::class);
        $formMetadata->getFlatFieldMetadata()->willReturn(['excerptCategories' => $categoriesField]);

        $this->formMetadataProvider->getMetadata('content_excerpt', 'en', Argument::type('array'))
            ->willReturn($formMetadata->reveal());

        $this->contentResolver->resolve([1, 2, 3], $categoriesField, 'en', Argument::type('array'))
            ->willReturn(new ContentView([['id' => 1], ['id' => 2], ['id' => 3]], []));

        $result = $this->excerptResolver->resolve(
            $dimensionContent->reveal(),
            ['myCategories' => 'excerpt.categories'],
            'en'
        );

        $this->assertSame(['myCategories' => [['id' => 1], ['id' => 2], ['id' => 3]]], $result->getContent());
    }

    public function testResolveWithTagsMapping(): void
    {
        $dimensionContent = $this->createTaxonomyDimensionContent();
        $dimensionContent->getExcerptCategoryIds()->willReturn([]);
        $dimensionContent->getExcerptTagNames()->willReturn(['tag1', 'tag2']);

        $tagsField = new FieldMetadata('excerptTags');
        $tagsField->setType('tag_selection');

        $formMetadata = $this->prophesize(FormMetadata::class);
        $formMetadata->getFlatFieldMetadata()->willReturn(['excerptTags' => $tagsField]);

        $this->formMetadataProvider->getMetadata('content_excerpt', 'en', Argument::type('array'))
            ->willReturn($formMetadata->reveal());

        $this->contentResolver->resolve(['tag1', 'tag2'], $tagsField, 'en', Argument::type('array'))
            ->willReturn(new ContentView(['tag1', 'tag2'], []));

        $result = $this->excerptResolver->resolve(
            $dimensionContent->reveal(),
            ['myTags' => 'excerpt.tags'],
            'en'
        );

        $this->assertSame(['myTags' => ['tag1', 'tag2']], $result->getContent());
    }

    public function testResolveWithNullContent(): void
    {
        $dimensionContent = $this->createExcerptDimensionContent();
        $dimensionContent->getExcerptData()->willReturn([]);

        $mediaField = new FieldMetadata('excerpt/media');
        $mediaField->setType('media_selection');

        $formMetadata = $this->prophesize(FormMetadata::class);
        $formMetadata->getFlatFieldMetadata()->willReturn(['excerpt/media' => $mediaField]);

        $this->formMetadataProvider->getMetadata('content_excerpt', 'en', Argument::type('array'))
            ->willReturn($formMetadata->reveal());

        $this->contentResolver->resolve(null, $mediaField, 'en', Argument::type('array'))
            ->willReturn(new ContentView(null, []));

        $result = $this->excerptResolver->resolve(
            $dimensionContent->reveal(),
            ['myMedia' => 'excerpt.media'],
            'en'
        );

        $this->assertSame(['myMedia' => []], $result->getContent());
    }

    public function testResolveWithNullContentTextType(): void
    {
        $dimensionContent = $this->createExcerptDimensionContent();
        $dimensionContent->getExcerptData()->willReturn([]);

        $descField = new FieldMetadata('excerpt/description');
        $descField->setType('text_area');

        $formMetadata = $this->prophesize(FormMetadata::class);
        $formMetadata->getFlatFieldMetadata()->willReturn(['excerpt/description' => $descField]);

        $this->formMetadataProvider->getMetadata('content_excerpt', 'en', Argument::type('array'))
            ->willReturn($formMetadata->reveal());

        $this->contentResolver->resolve(null, $descField, 'en', Argument::type('array'))
            ->willReturn(new ContentView(null, []));

        $result = $this->excerptResolver->resolve(
            $dimensionContent->reveal(),
            ['myDesc' => 'excerpt.description'],
            'en'
        );

        $this->assertSame(['myDesc' => ''], $result->getContent());
    }

    public function testResolveFieldNotInMetadata(): void
    {
        $dimensionContent = $this->createExcerptDimensionContent();
        $dimensionContent->getExcerptData()->willReturn(['title' => 'Excerpt Title']);

        $formMetadata = $this->prophesize(FormMetadata::class);
        $formMetadata->getFlatFieldMetadata()->willReturn([]);

        $this->formMetadataProvider->getMetadata('content_excerpt', 'en', Argument::type('array'))
            ->willReturn($formMetadata->reveal());

        $result = $this->excerptResolver->resolve(
            $dimensionContent->reveal(),
            ['myTitle' => 'excerpt.unknown'],
            'en'
        );

        $this->assertSame([], $result->getContent());
    }

    public function testHasExcerptPropertiesTrue(): void
    {
        $properties = [
            'title' => 'title',
            'myExcerpt' => 'excerpt.title',
        ];

        $this->assertTrue($this->excerptResolver->hasExcerptProperties($properties));
    }

    public function testHasExcerptPropertiesFalse(): void
    {
        $properties = [
            'title' => 'title',
            'seoTitle' => 'seo.title',
        ];

        $this->assertFalse($this->excerptResolver->hasExcerptProperties($properties));
    }

    private function createExcerptDimensionContent(): ObjectProphecy
    {
        return $this->prophesize(DimensionContentInterface::class)
            ->willImplement(ExcerptInterface::class);
    }

    private function createTaxonomyDimensionContent(): ObjectProphecy
    {
        return $this->prophesize(DimensionContentInterface::class)
            ->willImplement(TaxonomyInterface::class);
    }
}
