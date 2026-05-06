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

namespace Sulu\Bundle\HeadlessBundle\Tests\Unit\Content;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TagMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TypedFormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderInterface;
use Sulu\Bundle\HeadlessBundle\Content\ContentResolverInterface;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\ExtensionResolver\ExcerptResolver;
use Sulu\Bundle\HeadlessBundle\Content\ExtensionResolver\ExtensionResolverProvider;
use Sulu\Bundle\HeadlessBundle\Content\ExtensionResolver\SeoResolver;
use Sulu\Bundle\HeadlessBundle\Content\StructureResolver;
use Sulu\Bundle\HttpCacheBundle\ReferenceStore\ReferenceStoreInterface;
use Sulu\Content\Application\ContentDataMapper\DataMapper\TemplateDataMapper;
use Sulu\Page\Domain\Model\Page;
use Sulu\Page\Domain\Model\PageDimensionContent;
use Sulu\Route\Domain\Model\Route;

class StructureResolverTest extends TestCase
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

    /**
     * @var ObjectProphecy<ReferenceStoreInterface>
     */
    private ObjectProphecy $referenceStore;

    private StructureResolver $structureResolver;

    protected function setUp(): void
    {
        $this->formMetadataProvider = $this->prophesize(MetadataProviderInterface::class);
        $this->contentResolver = $this->prophesize(ContentResolverInterface::class);
        $this->referenceStore = $this->prophesize(ReferenceStoreInterface::class);

        // Create real extension resolvers
        $excerptResolver = new ExcerptResolver(
            $this->formMetadataProvider->reveal(),
            $this->contentResolver->reveal(),
        );
        $seoResolver = new SeoResolver(
            $this->formMetadataProvider->reveal(),
            $this->contentResolver->reveal(),
        );

        // Create provider with resolvers
        $extensionResolverProvider = new ExtensionResolverProvider([$excerptResolver, $seoResolver]);

        $this->structureResolver = new StructureResolver(
            $this->formMetadataProvider->reveal(),
            $this->contentResolver->reveal(),
            $this->referenceStore->reveal(),
            $extensionResolverProvider,
        );
    }

    public function testResolve(): void
    {
        $page = new Page('123-123-123');
        $page->setWebspaceKey('sulu_io');
        $page->setCreated(new \DateTimeImmutable('2024-01-01 10:00:00'));
        $page->setChanged(new \DateTimeImmutable('2024-01-02 15:00:00'));

        $dimensionContent = new PageDimensionContent($page);
        $dimensionContent->setTemplateKey('default');
        $dimensionContent->setTemplateData([
            'title' => 'Test Title',
            'media' => ['ids' => [1, 2, 3]],
        ]);

        // Set up form metadata
        $titleField = new FieldMetadata('title');
        $titleField->setType('text_line');
        $mediaField = new FieldMetadata('media');
        $mediaField->setType('media_selection');

        $formMetadata = $this->prophesize(FormMetadata::class);
        $formMetadata->getFlatFieldMetadata()->willReturn([
            'title' => $titleField,
            'media' => $mediaField,
        ]);

        $typedFormMetadata = $this->prophesize(TypedFormMetadata::class);
        $typedFormMetadata->getForms()->willReturn(['default' => $formMetadata->reveal()]);

        $this->formMetadataProvider->getMetadata('page', 'en', [])->willReturn($typedFormMetadata->reveal());

        // Mock content resolution
        $this->contentResolver->resolve('Test Title', $titleField, 'en', Argument::type('array'))
            ->willReturn(new ContentView('Test Title', []));

        $this->contentResolver->resolve(['ids' => [1, 2, 3]], $mediaField, 'en', Argument::type('array'))
            ->willReturn(new ContentView(['media1', 'media2'], ['ids' => [1, 2, 3]]));

        // Reference store
        $this->referenceStore->add('123-123-123', 'pages')->shouldBeCalled();

        /** @var array{id: string, type: string, template: string, content: array<string, mixed>, view: array<string, mixed>} $result */
        $result = $this->structureResolver->resolve($dimensionContent, 'en', false);

        $this->assertSame('123-123-123', $result['id']);
        $this->assertSame('page', $result['type']);
        $this->assertSame('default', $result['template']);
        $this->assertSame('Test Title', $result['content']['title']);
        $this->assertSame(['media1', 'media2'], $result['content']['media']);
        $this->assertSame([], $result['view']['title']);
        $this->assertSame(['ids' => [1, 2, 3]], $result['view']['media']);
    }

    public function testResolveWithExtensions(): void
    {
        $page = new Page('123-123-123');
        $page->setWebspaceKey('sulu_io');
        $page->setCreated(new \DateTimeImmutable('2024-01-01 10:00:00'));
        $page->setChanged(new \DateTimeImmutable('2024-01-02 15:00:00'));

        $dimensionContent = new PageDimensionContent($page);
        $dimensionContent->setTemplateKey('default');
        $dimensionContent->setTemplateData(['title' => 'Test']);
        $dimensionContent->setExcerptData(['title' => 'Excerpt Title']);
        $dimensionContent->setSeoData(['title' => 'SEO Title']);

        // Template form metadata
        $titleField = new FieldMetadata('title');
        $titleField->setType('text_line');

        $formMetadata = $this->prophesize(FormMetadata::class);
        $formMetadata->getFlatFieldMetadata()->willReturn(['title' => $titleField]);

        $typedFormMetadata = $this->prophesize(TypedFormMetadata::class);
        $typedFormMetadata->getForms()->willReturn(['default' => $formMetadata->reveal()]);

        $this->formMetadataProvider->getMetadata('page', 'en', [])->willReturn($typedFormMetadata->reveal());

        // Excerpt form metadata (field names are prefixed with 'excerpt/')
        $excerptTitleField = new FieldMetadata('excerpt/title');
        $excerptTitleField->setType('text_line');

        $excerptFormMetadata = $this->prophesize(FormMetadata::class);
        $excerptFormMetadata->getFlatFieldMetadata()->willReturn(['excerpt/title' => $excerptTitleField]);

        $this->formMetadataProvider->getMetadata('content_excerpt', 'en', Argument::type('array'))->willReturn($excerptFormMetadata->reveal());

        // SEO form metadata (field names are prefixed with 'seo/')
        $seoTitleField = new FieldMetadata('seo/title');
        $seoTitleField->setType('text_line');

        $seoFormMetadata = $this->prophesize(FormMetadata::class);
        $seoFormMetadata->getFlatFieldMetadata()->willReturn(['seo/title' => $seoTitleField]);

        $this->formMetadataProvider->getMetadata('content_seo', 'en', Argument::type('array'))->willReturn($seoFormMetadata->reveal());

        // Mock content resolution
        $this->contentResolver->resolve('Test', $titleField, 'en', Argument::type('array'))
            ->willReturn(new ContentView('Test', []));
        $this->contentResolver->resolve('Excerpt Title', $excerptTitleField, 'en', Argument::type('array'))
            ->willReturn(new ContentView('Excerpt Title', []));
        $this->contentResolver->resolve('SEO Title', $seoTitleField, 'en', Argument::type('array'))
            ->willReturn(new ContentView('SEO Title', []));

        $this->referenceStore->add('123-123-123', 'pages')->shouldBeCalled();

        /** @var array{extension: array{excerpt: array<string, mixed>, seo: array<string, mixed>}} $result */
        $result = $this->structureResolver->resolve($dimensionContent, 'en', true);

        $this->assertSame('Excerpt Title', $result['extension']['excerpt']['title']);
        $this->assertSame('SEO Title', $result['extension']['seo']['title']);
    }

    public function testResolveProperties(): void
    {
        $page = new Page('123-123-123');
        $page->setWebspaceKey('sulu_io');
        $page->setCreated(new \DateTimeImmutable('2024-01-01 10:00:00'));
        $page->setChanged(new \DateTimeImmutable('2024-01-02 15:00:00'));

        $dimensionContent = new PageDimensionContent($page);
        $dimensionContent->setTemplateKey('default');
        $dimensionContent->setTemplateData([
            'title' => 'Test Title',
            'description' => 'Test Description',
            'media' => ['ids' => [1]],
        ]);

        // Set up form metadata with all fields
        $titleField = new FieldMetadata('title');
        $titleField->setType('text_line');
        $descriptionField = new FieldMetadata('description');
        $descriptionField->setType('text_area');
        $mediaField = new FieldMetadata('media');
        $mediaField->setType('media_selection');

        $formMetadata = $this->prophesize(FormMetadata::class);
        $formMetadata->getFlatFieldMetadata()->willReturn([
            'title' => $titleField,
            'description' => $descriptionField,
            'media' => $mediaField,
        ]);

        $typedFormMetadata = $this->prophesize(TypedFormMetadata::class);
        $typedFormMetadata->getForms()->willReturn(['default' => $formMetadata->reveal()]);

        $this->formMetadataProvider->getMetadata('page', 'en', [])->willReturn($typedFormMetadata->reveal());

        // Only title should be resolved (mapped to 'myTitle')
        $this->contentResolver->resolve('Test Title', $titleField, 'en', Argument::type('array'))
            ->willReturn(new ContentView('Test Title', []));

        $this->referenceStore->add('123-123-123', 'pages')->shouldBeCalled();

        // Only request title, mapped to 'myTitle'
        /** @var array{id: string, content: array<string, mixed>} $result */
        $result = $this->structureResolver->resolveProperties(
            $dimensionContent,
            ['myTitle' => 'title'],
            'en',
        );

        $this->assertSame('123-123-123', $result['id']);
        $this->assertSame('Test Title', $result['content']['myTitle']);
        $this->assertArrayNotHasKey('description', $result['content']);
        $this->assertArrayNotHasKey('media', $result['content']);
    }

    public function testResolveEmptyTemplate(): void
    {
        $page = new Page('123-123-123');
        $page->setWebspaceKey('sulu_io');
        $page->setCreated(new \DateTimeImmutable('2024-01-01 10:00:00'));
        $page->setChanged(new \DateTimeImmutable('2024-01-02 15:00:00'));

        $dimensionContent = new PageDimensionContent($page);
        // No template key set

        $this->referenceStore->add('123-123-123', 'pages')->shouldBeCalled();

        $result = $this->structureResolver->resolve($dimensionContent, 'en', false);

        $this->assertSame('123-123-123', $result['id']);
        $this->assertSame('page', $result['type']);
        $this->assertNull($result['template']);
        $this->assertSame([], $result['content']);
        $this->assertSame([], $result['view']);
    }

    public function testResolveTemplateNotInForms(): void
    {
        $page = new Page('123-123-123');
        $page->setWebspaceKey('sulu_io');
        $page->setCreated(new \DateTimeImmutable('2024-01-01 10:00:00'));
        $page->setChanged(new \DateTimeImmutable('2024-01-02 15:00:00'));

        $dimensionContent = new PageDimensionContent($page);
        $dimensionContent->setTemplateKey('nonexistent');
        $dimensionContent->setTemplateData(['title' => 'Test']);

        $typedFormMetadata = $this->prophesize(TypedFormMetadata::class);
        $typedFormMetadata->getForms()->willReturn([]);

        $this->formMetadataProvider->getMetadata('page', 'en', [])->willReturn($typedFormMetadata->reveal());
        $this->referenceStore->add('123-123-123', 'pages')->shouldBeCalled();

        $result = $this->structureResolver->resolve($dimensionContent, 'en', false);

        $this->assertSame('123-123-123', $result['id']);
        $this->assertSame('nonexistent', $result['template']);
        $this->assertSame([], $result['content']);
        $this->assertSame([], $result['view']);
    }

    public function testResolvePropertiesWithExtensionProperties(): void
    {
        $page = new Page('123-123-123');
        $page->setWebspaceKey('sulu_io');
        $page->setCreated(new \DateTimeImmutable('2024-01-01 10:00:00'));
        $page->setChanged(new \DateTimeImmutable('2024-01-02 15:00:00'));

        $dimensionContent = new PageDimensionContent($page);
        $dimensionContent->setTemplateKey('default');
        $dimensionContent->setTemplateData(['title' => 'Test Title']);
        $dimensionContent->setExcerptData(['title' => 'Excerpt Title']);

        $titleField = new FieldMetadata('title');
        $titleField->setType('text_line');

        $formMetadata = $this->prophesize(FormMetadata::class);
        $formMetadata->getFlatFieldMetadata()->willReturn(['title' => $titleField]);

        $typedFormMetadata = $this->prophesize(TypedFormMetadata::class);
        $typedFormMetadata->getForms()->willReturn(['default' => $formMetadata->reveal()]);

        $this->formMetadataProvider->getMetadata('page', 'en', [])->willReturn($typedFormMetadata->reveal());

        $excerptTitleField = new FieldMetadata('excerpt/title');
        $excerptTitleField->setType('text_line');

        $excerptFormMetadata = $this->prophesize(FormMetadata::class);
        $excerptFormMetadata->getFlatFieldMetadata()->willReturn(['excerpt/title' => $excerptTitleField]);

        $this->formMetadataProvider->getMetadata('content_excerpt', 'en', Argument::type('array'))
            ->willReturn($excerptFormMetadata->reveal());

        $this->contentResolver->resolve('Test Title', $titleField, 'en', Argument::type('array'))
            ->willReturn(new ContentView('Test Title', []));
        $this->contentResolver->resolve('Excerpt Title', $excerptTitleField, 'en', Argument::type('array'))
            ->willReturn(new ContentView('Excerpt Title', []));

        $this->referenceStore->add('123-123-123', 'pages')->shouldBeCalled();

        /** @var array{content: array<string, mixed>} $result */
        $result = $this->structureResolver->resolveProperties(
            $dimensionContent,
            [
                'myTitle' => 'title',
                'myExcerptTitle' => 'excerpt.title',
            ],
            'en',
        );

        $this->assertSame('Test Title', $result['content']['myTitle']);
        $this->assertSame('Excerpt Title', $result['content']['myExcerptTitle']);
    }

    public function testResolveWithShadowLocale(): void
    {
        $page = new Page('123-123-123');
        $page->setWebspaceKey('sulu_io');
        $page->setCreated(new \DateTimeImmutable('2024-01-01 10:00:00'));
        $page->setChanged(new \DateTimeImmutable('2024-01-02 15:00:00'));

        $dimensionContent = new PageDimensionContent($page);
        $dimensionContent->setTemplateKey('default');
        $dimensionContent->setTemplateData(['title' => 'Test']);
        $dimensionContent->setShadowLocale('de');

        $titleField = new FieldMetadata('title');
        $titleField->setType('text_line');

        $formMetadata = $this->prophesize(FormMetadata::class);
        $formMetadata->getFlatFieldMetadata()->willReturn(['title' => $titleField]);

        $typedFormMetadata = $this->prophesize(TypedFormMetadata::class);
        $typedFormMetadata->getForms()->willReturn(['default' => $formMetadata->reveal()]);

        $this->formMetadataProvider->getMetadata('page', 'en', [])->willReturn($typedFormMetadata->reveal());

        $this->contentResolver->resolve('Test', $titleField, 'en', Argument::that(static function ($attributes) {
            return isset($attributes['isShadow']) && true === $attributes['isShadow']
                && isset($attributes['shadowLocale']) && 'de' === $attributes['shadowLocale'];
        }))->willReturn(new ContentView('Test', []));

        $this->referenceStore->add('123-123-123', 'pages')->shouldBeCalled();

        /** @var array{content: array<string, mixed>} $result */
        $result = $this->structureResolver->resolve($dimensionContent, 'en', false);

        $this->assertSame('Test', $result['content']['title']);
    }

    public function testResolveWithAuthorData(): void
    {
        $page = new Page('123-123-123');
        $page->setWebspaceKey('sulu_io');
        $page->setCreated(new \DateTimeImmutable('2024-01-01 10:00:00'));
        $page->setChanged(new \DateTimeImmutable('2024-01-02 15:00:00'));

        $dimensionContent = new PageDimensionContent($page);
        $dimensionContent->setTemplateKey('default');
        $dimensionContent->setTemplateData([]);
        $dimensionContent->setAuthored(new \DateTimeImmutable('2024-01-15 12:00:00', new \DateTimeZone('UTC')));

        $formMetadata = $this->prophesize(FormMetadata::class);
        $formMetadata->getFlatFieldMetadata()->willReturn([]);

        $typedFormMetadata = $this->prophesize(TypedFormMetadata::class);
        $typedFormMetadata->getForms()->willReturn(['default' => $formMetadata->reveal()]);

        $this->formMetadataProvider->getMetadata('page', 'en', [])->willReturn($typedFormMetadata->reveal());
        $this->referenceStore->add('123-123-123', 'pages')->shouldBeCalled();

        $result = $this->structureResolver->resolve($dimensionContent, 'en', false);

        $this->assertNull($result['author']);
        $this->assertSame('2024-01-15T12:00:00+00:00', $result['authored']);
    }

    public function testResolveRouteFieldFromRoute(): void
    {
        $route = new Route('pages', 'page-uuid', 'en', '/my-slug');

        $page = new Page('123-123-123');
        $page->setWebspaceKey('sulu_io');
        $page->setCreated(new \DateTimeImmutable('2024-01-01 10:00:00'));
        $page->setChanged(new \DateTimeImmutable('2024-01-02 15:00:00'));

        $dimensionContent = new PageDimensionContent($page);
        $dimensionContent->setTemplateKey('default');
        $dimensionContent->setTemplateData([]);
        $dimensionContent->setRoute($route);

        $tag = new TagMetadata();
        $tag->setName(TemplateDataMapper::SKIP_TAG);

        $urlField = new FieldMetadata('url');
        $urlField->setType('route');
        $urlField->addTag($tag);

        $formMetadata = $this->prophesize(FormMetadata::class);
        $formMetadata->getFlatFieldMetadata()->willReturn(['url' => $urlField]);

        $typedFormMetadata = $this->prophesize(TypedFormMetadata::class);
        $typedFormMetadata->getForms()->willReturn(['default' => $formMetadata->reveal()]);

        $this->formMetadataProvider->getMetadata('page', 'en', [])->willReturn($typedFormMetadata->reveal());

        $this->contentResolver->resolve('/my-slug', $urlField, 'en', Argument::type('array'))
            ->willReturn(new ContentView('/my-slug', []));

        $this->referenceStore->add('123-123-123', 'pages')->shouldBeCalled();

        /** @var array{content: array<string, mixed>} $result */
        $result = $this->structureResolver->resolve($dimensionContent, 'en', false);

        $this->assertSame('/my-slug', $result['content']['url']);
    }

    public function testResolvePageTreeRouteFieldFromRoute(): void
    {
        $parentRoute = new Route('pages', 'parent-uuid', 'en', '/parent');
        $route = new Route('pages', 'page-uuid', 'en', '/parent/child', null, $parentRoute);

        $page = new Page('123-123-123');
        $page->setWebspaceKey('sulu_io');
        $page->setCreated(new \DateTimeImmutable('2024-01-01 10:00:00'));
        $page->setChanged(new \DateTimeImmutable('2024-01-02 15:00:00'));

        $dimensionContent = new PageDimensionContent($page);
        $dimensionContent->setTemplateKey('default');
        $dimensionContent->setTemplateData([]);
        $dimensionContent->setRoute($route);

        $tag = new TagMetadata();
        $tag->setName(TemplateDataMapper::SKIP_TAG);

        $routeField = new FieldMetadata('url');
        $routeField->setType('page_tree_route');
        $routeField->addTag($tag);

        $formMetadata = $this->prophesize(FormMetadata::class);
        $formMetadata->getFlatFieldMetadata()->willReturn(['url' => $routeField]);

        $typedFormMetadata = $this->prophesize(TypedFormMetadata::class);
        $typedFormMetadata->getForms()->willReturn(['default' => $formMetadata->reveal()]);

        $this->formMetadataProvider->getMetadata('page', 'en', [])->willReturn($typedFormMetadata->reveal());

        $expectedValue = [
            'page' => ['uuid' => 'parent-uuid', 'path' => '/parent'],
            'suffix' => '/child',
        ];
        $this->contentResolver->resolve($expectedValue, $routeField, 'en', Argument::type('array'))
            ->willReturn(new ContentView($expectedValue, []));

        $this->referenceStore->add('123-123-123', 'pages')->shouldBeCalled();

        /** @var array{content: array<string, array{page: array{path: string, uuid: string}, suffix: string}>} $result */
        $result = $this->structureResolver->resolve($dimensionContent, 'en', false);

        $this->assertSame('/parent', $result['content']['url']['page']['path']);
        $this->assertSame('parent-uuid', $result['content']['url']['page']['uuid']);
        $this->assertSame('/child', $result['content']['url']['suffix']);
    }

    public function testResolvePageTreeRouteFieldNoParentRoute(): void
    {
        $route = new Route('pages', 'page-uuid', 'en', '/my-slug');

        $page = new Page('123-123-123');
        $page->setWebspaceKey('sulu_io');
        $page->setCreated(new \DateTimeImmutable('2024-01-01 10:00:00'));
        $page->setChanged(new \DateTimeImmutable('2024-01-02 15:00:00'));

        $dimensionContent = new PageDimensionContent($page);
        $dimensionContent->setTemplateKey('default');
        $dimensionContent->setTemplateData([]);
        $dimensionContent->setRoute($route);

        $tag = new TagMetadata();
        $tag->setName(TemplateDataMapper::SKIP_TAG);

        $routeField = new FieldMetadata('url');
        $routeField->setType('page_tree_route');
        $routeField->addTag($tag);

        $formMetadata = $this->prophesize(FormMetadata::class);
        $formMetadata->getFlatFieldMetadata()->willReturn(['url' => $routeField]);

        $typedFormMetadata = $this->prophesize(TypedFormMetadata::class);
        $typedFormMetadata->getForms()->willReturn(['default' => $formMetadata->reveal()]);

        $this->formMetadataProvider->getMetadata('page', 'en', [])->willReturn($typedFormMetadata->reveal());

        $this->contentResolver->resolve(null, $routeField, 'en', Argument::type('array'))
            ->willReturn(new ContentView(null, []));

        $this->referenceStore->add('123-123-123', 'pages')->shouldBeCalled();

        /** @var array{content: array<string, mixed>} $result */
        $result = $this->structureResolver->resolve($dimensionContent, 'en', false);

        $this->assertNull($result['content']['url']);
    }

    public function testResolvePageTreeRouteFieldSlugWithoutParentPrefix(): void
    {
        $parentRoute = new Route('pages', 'parent-uuid', 'en', '/parent');
        $route = new Route('pages', 'page-uuid', 'en', '/completely-different', null, $parentRoute);

        $page = new Page('123-123-123');
        $page->setWebspaceKey('sulu_io');
        $page->setCreated(new \DateTimeImmutable('2024-01-01 10:00:00'));
        $page->setChanged(new \DateTimeImmutable('2024-01-02 15:00:00'));

        $dimensionContent = new PageDimensionContent($page);
        $dimensionContent->setTemplateKey('default');
        $dimensionContent->setTemplateData([]);
        $dimensionContent->setRoute($route);

        $tag = new TagMetadata();
        $tag->setName(TemplateDataMapper::SKIP_TAG);

        $routeField = new FieldMetadata('url');
        $routeField->setType('page_tree_route');
        $routeField->addTag($tag);

        $formMetadata = $this->prophesize(FormMetadata::class);
        $formMetadata->getFlatFieldMetadata()->willReturn(['url' => $routeField]);

        $typedFormMetadata = $this->prophesize(TypedFormMetadata::class);
        $typedFormMetadata->getForms()->willReturn(['default' => $formMetadata->reveal()]);

        $this->formMetadataProvider->getMetadata('page', 'en', [])->willReturn($typedFormMetadata->reveal());

        $expectedValue = [
            'page' => ['uuid' => 'parent-uuid', 'path' => '/parent'],
            'suffix' => '',
        ];
        $this->contentResolver->resolve($expectedValue, $routeField, 'en', Argument::type('array'))
            ->willReturn(new ContentView($expectedValue, []));

        $this->referenceStore->add('123-123-123', 'pages')->shouldBeCalled();

        /** @var array{content: array<string, array{page: array{path: string, uuid: string}, suffix: string}>} $result */
        $result = $this->structureResolver->resolve($dimensionContent, 'en', false);

        $this->assertSame('', $result['content']['url']['suffix']);
    }
}
