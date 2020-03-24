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
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\AudienceTargetingBundle\TargetGroup\TargetGroupStoreInterface;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\SmartContentResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\DataProviderResolver\DataProviderResolverInterface;
use Sulu\Bundle\HeadlessBundle\Content\DataProviderResolver\DataProviderResult;
use Sulu\Bundle\TagBundle\Tag\TagManagerInterface;
use Sulu\Component\Category\Request\CategoryRequestHandlerInterface;
use Sulu\Component\Content\Compat\PropertyInterface;
use Sulu\Component\Content\Compat\PropertyParameter;
use Sulu\Component\Content\Compat\StructureInterface;
use Sulu\Component\SmartContent\Configuration\ProviderConfigurationInterface;
use Sulu\Component\Tag\Request\TagRequestHandlerInterface;
use Sulu\Exception\FeatureNotImplementedException;
use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class SmartContentResolverTest extends TestCase
{
    /**
     * @var DataProviderResolverInterface|ObjectProphecy
     */
    private $mediaProviderResolver;

    /**
     * @var RequestStack|ObjectProphecy
     */
    private $requestStack;

    /**
     * @var TagManagerInterface|ObjectProphecy
     */
    private $tagManager;

    /**
     * @var TagRequestHandlerInterface|ObjectProphecy
     */
    private $tagRequestHandler;

    /**
     * @var CategoryRequestHandlerInterface|ObjectProphecy
     */
    private $categoryRequestHandler;

    /**
     * @var TargetGroupStoreInterface|ObjectProphecy
     */
    private $targetGroupStore;

    /**
     * @var SmartContentResolver
     */
    private $smartContentResolver;

    protected function setUp(): void
    {
        $this->mediaProviderResolver = $this->prophesize(DataProviderResolverInterface::class);
        $this->requestStack = $this->prophesize(RequestStack::class);
        $this->tagManager = $this->prophesize(TagManagerInterface::class);
        $this->tagRequestHandler = $this->prophesize(TagRequestHandlerInterface::class);
        $this->categoryRequestHandler = $this->prophesize(CategoryRequestHandlerInterface::class);
        $this->targetGroupStore = $this->prophesize(TargetGroupStoreInterface::class);

        $this->smartContentResolver = new SmartContentResolver(
            new \ArrayIterator(['media' => $this->mediaProviderResolver->reveal()]),
            $this->tagManager->reveal(),
            $this->requestStack->reveal(),
            $this->tagRequestHandler->reveal(),
            $this->categoryRequestHandler->reveal(),
            $this->targetGroupStore->reveal()
        );
    }

    public function testGetContentType(): void
    {
        self::assertSame('smart_content', $this->smartContentResolver::getContentType());
    }

    public function testResolve(): void
    {
        $property = $this->prophesize(PropertyInterface::class);
        $property->getParams()->willReturn(['provider' => new PropertyParameter('provider', 'media')]);
        $property->getValue()->willReturn([
            'tags' => [111, 'tag-name-1'],
            'categories' => [123],
            'limitResult' => 10,
        ]);

        $structure = $this->prophesize(StructureInterface::class);
        $structure->getUuid()->willReturn('uuid-123');
        $structure->getWebspaceKey()->willReturn('webspace-key');
        $structure->getLanguageCode()->willReturn('locale-123');
        $property->getStructure()->willReturn($structure->reveal());

        $configuration = $this->prophesize(ProviderConfigurationInterface::class);
        $configuration->getSorting()->willReturn(null);
        $configuration->hasDatasource()->willReturn(true);
        $configuration->hasTags()->willReturn(true);
        $configuration->hasCategories()->willReturn(true);
        $configuration->hasSorting()->willReturn(true);
        $configuration->hasLimit()->willReturn(true);
        $configuration->hasPagination()->willReturn(false);
        $configuration->hasPresentAs()->willReturn(false);
        $configuration->hasAudienceTargeting()->willReturn(false);
        $configuration->getDatasourceResourceKey()->willReturn(null);
        $configuration->getDatasourceAdapter()->willReturn(null);
        $this->mediaProviderResolver->getProviderConfiguration()->willReturn($configuration->reveal());
        $this->mediaProviderResolver->getProviderDefaultParams()->willReturn([
            'website_tags_operator' => new PropertyParameter('website_tags_operator', 'AND'),
        ]);

        $this->tagRequestHandler->getTags('tags')->willReturn(['tag-name-2']);
        $this->categoryRequestHandler->getCategories('categories')->willReturn([456]);

        $this->tagManager->resolveTagNames(['tag-name-1'])->willReturn([222]);
        $this->tagManager->resolveTagNames(['tag-name-2'])->willReturn([333]);

        $providerResult = $this->prophesize(DataProviderResult::class);
        $providerResult->getHasNextPage()->willReturn(false);
        $providerResult->getItems()->willReturn([['id' => 'id-1'], ['id' => 'id-2']]);
        $this->mediaProviderResolver->resolve(
            [
                'tags' => [111, 222],
                'categories' => [123],
                'limitResult' => 10,
                'excluded' => ['uuid-123'],
                'websiteTags' => [333],
                'websiteTagsOperator' => 'AND',
                'websiteCategories' => [456],
                'websiteCategoriesOperator' => 'OR',
            ],
            Argument::any(),
            ['webspaceKey' => 'webspace-key', 'locale' => 'locale-123'],
            10
        )->willReturn($providerResult->reveal());

        $result = $this->smartContentResolver->resolve([], $property->reveal(), 'en');

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame(
            [['id' => 'id-1'], ['id' => 'id-2']],
            $result->getContent()
        );
        $this->assertSame(
            [
                'tags' => [111, 222],
                'categories' => [123],
                'limitResult' => 10,
                'excluded' => ['uuid-123'],
                'websiteTags' => [333],
                'websiteTagsOperator' => 'AND',
                'websiteCategories' => [456],
                'websiteCategoriesOperator' => 'OR',
                'page' => 1,
                'hasNextPage' => false,
                'paginated' => false,
            ],
            $result->getView()
        );
    }

    public function testResolvePaginated(): void
    {
        $property = $this->prophesize(PropertyInterface::class);
        $property->getParams()->willReturn([
            'provider' => new PropertyParameter('provider', 'media'),
            'max_per_page' => new PropertyParameter('max_per_page', '5'),
            'page_parameter' => new PropertyParameter('max_per_page', 'page'),
        ]);
        $property->getValue()->willReturn([
            'tags' => [111, 'tag-name-1'],
            'categories' => [123],
            'limitResult' => 10,
        ]);

        $structure = $this->prophesize(StructureInterface::class);
        $structure->getUuid()->willReturn('uuid-123');
        $structure->getWebspaceKey()->willReturn('webspace-key');
        $structure->getLanguageCode()->willReturn('locale-123');
        $property->getStructure()->willReturn($structure->reveal());

        $configuration = $this->prophesize(ProviderConfigurationInterface::class);
        $configuration->getSorting()->willReturn(null);
        $configuration->hasDatasource()->willReturn(true);
        $configuration->hasTags()->willReturn(true);
        $configuration->hasCategories()->willReturn(true);
        $configuration->hasSorting()->willReturn(true);
        $configuration->hasLimit()->willReturn(true);
        $configuration->hasPagination()->willReturn(true);
        $configuration->hasPresentAs()->willReturn(false);
        $configuration->hasAudienceTargeting()->willReturn(false);
        $configuration->getDatasourceResourceKey()->willReturn(null);
        $configuration->getDatasourceAdapter()->willReturn(null);
        $this->mediaProviderResolver->getProviderConfiguration()->willReturn($configuration->reveal());
        $this->mediaProviderResolver->getProviderDefaultParams()->willReturn([
            'website_tags_operator' => new PropertyParameter('website_tags_operator', 'AND'),
        ]);

        $this->tagRequestHandler->getTags('tags')->willReturn(['tag-name-2']);
        $this->categoryRequestHandler->getCategories('categories')->willReturn([456]);

        $this->tagManager->resolveTagNames(['tag-name-1'])->willReturn([222]);
        $this->tagManager->resolveTagNames(['tag-name-2'])->willReturn([333]);

        $request = $this->prophesize(Request::class);
        $request->get('page', 1)->willReturn(2);
        $this->requestStack->getCurrentRequest()->willReturn($request->reveal());

        $providerResult = $this->prophesize(DataProviderResult::class);
        $providerResult->getHasNextPage()->willReturn(false);
        $providerResult->getItems()->willReturn([['id' => 'id-1'], ['id' => 'id-2']]);
        $this->mediaProviderResolver->resolve(
            [
                'tags' => [111, 222],
                'categories' => [123],
                'limitResult' => 10,
                'excluded' => ['uuid-123'],
                'websiteTags' => [333],
                'websiteTagsOperator' => 'AND',
                'websiteCategories' => [456],
                'websiteCategoriesOperator' => 'OR',
            ],
            Argument::any(),
            ['webspaceKey' => 'webspace-key', 'locale' => 'locale-123'],
            10,
            2,
            5
        )->willReturn($providerResult->reveal());

        $result = $this->smartContentResolver->resolve([], $property->reveal(), 'en');

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame(
            [['id' => 'id-1'], ['id' => 'id-2']],
            $result->getContent()
        );
        $this->assertSame(
            [
                'tags' => [111, 222],
                'categories' => [123],
                'limitResult' => 10,
                'excluded' => ['uuid-123'],
                'websiteTags' => [333],
                'websiteTagsOperator' => 'AND',
                'websiteCategories' => [456],
                'websiteCategoriesOperator' => 'OR',
                'page' => 2,
                'hasNextPage' => false,
                'paginated' => true,
            ],
            $result->getView()
        );
    }

    public function testResolveMissingProviderResolver(): void
    {
        $this->expectException(FeatureNotImplementedException::class);

        $property = $this->prophesize(PropertyInterface::class);
        $property->getParams()->willReturn(['provider' => new PropertyParameter('provider', 'contact')]);

        $this->smartContentResolver->resolve([], $property->reveal(), 'en');
    }
}
