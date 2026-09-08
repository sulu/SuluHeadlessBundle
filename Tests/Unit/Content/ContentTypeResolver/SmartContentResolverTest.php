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
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\OptionMetadata;
use Sulu\Bundle\AdminBundle\SmartContent\Configuration\ProviderConfigurationInterface;
use Sulu\Bundle\AudienceTargetingBundle\TargetGroup\TargetGroupStoreInterface;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\SmartContentResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\DataProviderResolver\DataProviderResolverInterface;
use Sulu\Bundle\HeadlessBundle\Content\DataProviderResolver\DataProviderResult;
use Sulu\Bundle\TagBundle\Tag\TagInterface;
use Sulu\Bundle\TagBundle\Tag\TagManagerInterface;
use Sulu\Component\Category\Request\CategoryRequestHandlerInterface;
use Sulu\Component\Content\Compat\PropertyParameter;
use Sulu\Component\Tag\Request\TagRequestHandlerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class SmartContentResolverTest extends TestCase
{
    use ProphecyTrait;

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

    private FieldMetadata $fieldMetadata;

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

        $this->fieldMetadata = new FieldMetadata('smart_content');

        // Set provider option to 'media' to match the resolver we registered
        $providerOption = new OptionMetadata();
        $providerOption->setName('provider');
        $providerOption->setValue('media');
        $this->fieldMetadata->addOption($providerOption);

        // Set max_per_page option for paginated test
        $maxPerPageOption = new OptionMetadata();
        $maxPerPageOption->setName('max_per_page');
        $maxPerPageOption->setValue(5);
        $this->fieldMetadata->addOption($maxPerPageOption);
    }

    public function testGetContentType(): void
    {
        self::assertSame('smart_content', $this->smartContentResolver::getContentType());
    }

    public function testResolve(): void
    {
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

        // Mock tag resolution via findByName
        $tag1 = $this->prophesize(TagInterface::class);
        $tag1->getId()->willReturn(222);
        $this->tagManager->findByName('tag-name-1')->willReturn($tag1->reveal());

        $tag2 = $this->prophesize(TagInterface::class);
        $tag2->getId()->willReturn(333);
        $this->tagManager->findByName('tag-name-2')->willReturn($tag2->reveal());

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
            ['webspaceKey' => 'webspace-key', 'locale' => 'en'],
            10
        )->willReturn($providerResult->reveal());

        $data = [
            'tags' => [111, 'tag-name-1'],
            'categories' => [123],
            'limitResult' => 10,
        ];

        $result = $this->smartContentResolver->resolve($data, $this->fieldMetadata, 'en', [
            'uuid' => 'uuid-123',
            'webspaceKey' => 'webspace-key',
        ]);

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

        // Mock tag resolution via findByName
        $tag1 = $this->prophesize(TagInterface::class);
        $tag1->getId()->willReturn(222);
        $this->tagManager->findByName('tag-name-1')->willReturn($tag1->reveal());

        $tag2 = $this->prophesize(TagInterface::class);
        $tag2->getId()->willReturn(333);
        $this->tagManager->findByName('tag-name-2')->willReturn($tag2->reveal());

        $this->requestStack->getCurrentRequest()->willReturn(new Request(['p' => 2]));

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
            ['webspaceKey' => 'webspace-key', 'locale' => 'en'],
            10,
            2,
            5
        )->willReturn($providerResult->reveal());

        $data = [
            'tags' => [111, 'tag-name-1'],
            'categories' => [123],
            'limitResult' => 10,
        ];

        $result = $this->smartContentResolver->resolve($data, $this->fieldMetadata, 'en', [
            'uuid' => 'uuid-123',
            'webspaceKey' => 'webspace-key',
        ]);

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
        // Create a field metadata with a non-existent provider
        $fieldMetadata = new FieldMetadata('smart_content');
        $providerOption = new OptionMetadata();
        $providerOption->setName('provider');
        $providerOption->setValue('non_existent_provider');
        $fieldMetadata->addOption($providerOption);

        $result = $this->smartContentResolver->resolve(['key' => 'value'], $fieldMetadata, 'en');

        self::assertNull($result->getContent());
        self::assertSame(['key' => 'value'], $result->getView());
    }

    public function testResolvePaginatedWithNullRequest(): void
    {
        $configuration = $this->prophesize(ProviderConfigurationInterface::class);
        $configuration->getSorting()->willReturn(null);
        $configuration->hasDatasource()->willReturn(false);
        $configuration->hasTags()->willReturn(false);
        $configuration->hasCategories()->willReturn(false);
        $configuration->hasSorting()->willReturn(false);
        $configuration->hasLimit()->willReturn(true);
        $configuration->hasPagination()->willReturn(true);
        $configuration->hasPresentAs()->willReturn(false);
        $configuration->hasAudienceTargeting()->willReturn(false);
        $configuration->getDatasourceResourceKey()->willReturn(null);
        $configuration->getDatasourceAdapter()->willReturn(null);
        $this->mediaProviderResolver->getProviderConfiguration()->willReturn($configuration->reveal());
        $this->mediaProviderResolver->getProviderDefaultParams()->willReturn([]);

        $this->tagRequestHandler->getTags('tags')->willReturn([]);
        $this->categoryRequestHandler->getCategories('categories')->willReturn([]);

        $this->requestStack->getCurrentRequest()->willReturn(null);

        $providerResult = $this->prophesize(DataProviderResult::class);
        $providerResult->getHasNextPage()->willReturn(false);
        $providerResult->getItems()->willReturn([]);
        $this->mediaProviderResolver->resolve(
            Argument::any(),
            Argument::any(),
            Argument::any(),
            Argument::any(),
            1,
            5
        )->willReturn($providerResult->reveal());

        $maxPerPageOption = new OptionMetadata();
        $maxPerPageOption->setName('max_per_page');
        $maxPerPageOption->setValue(5);
        $this->fieldMetadata->addOption($maxPerPageOption);

        $result = $this->smartContentResolver->resolve([], $this->fieldMetadata, 'en', []);

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame(1, $result->getView()['page']);
    }

    public function testResolveWithAudienceTargeting(): void
    {
        $configuration = $this->prophesize(ProviderConfigurationInterface::class);
        $configuration->getSorting()->willReturn(null);
        $configuration->hasDatasource()->willReturn(false);
        $configuration->hasTags()->willReturn(false);
        $configuration->hasCategories()->willReturn(false);
        $configuration->hasSorting()->willReturn(false);
        $configuration->hasLimit()->willReturn(true);
        $configuration->hasPagination()->willReturn(false);
        $configuration->hasPresentAs()->willReturn(false);
        $configuration->hasAudienceTargeting()->willReturn(true);
        $configuration->getDatasourceResourceKey()->willReturn(null);
        $configuration->getDatasourceAdapter()->willReturn(null);
        $this->mediaProviderResolver->getProviderConfiguration()->willReturn($configuration->reveal());
        $this->mediaProviderResolver->getProviderDefaultParams()->willReturn([]);

        $this->tagRequestHandler->getTags('tags')->willReturn([]);
        $this->categoryRequestHandler->getCategories('categories')->willReturn([]);

        $this->targetGroupStore->getTargetGroupId()->willReturn(42);

        $providerResult = $this->prophesize(DataProviderResult::class);
        $providerResult->getHasNextPage()->willReturn(false);
        $providerResult->getItems()->willReturn([['id' => 'targeted-item']]);
        $this->mediaProviderResolver->resolve(
            Argument::that(static function ($filters) {
                return isset($filters['targetGroupId']) && 42 === $filters['targetGroupId'];
            }),
            Argument::any(),
            Argument::any(),
            Argument::any()
        )->willReturn($providerResult->reveal());

        $data = [
            'audienceTargeting' => true,
            'limitResult' => 5,
        ];

        $result = $this->smartContentResolver->resolve($data, $this->fieldMetadata, 'en', []);

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame([['id' => 'targeted-item']], $result->getContent());
    }

    public function testResolveWithNonStringOptionName(): void
    {
        $configuration = $this->prophesize(ProviderConfigurationInterface::class);
        $configuration->getSorting()->willReturn(null);
        $configuration->hasDatasource()->willReturn(false);
        $configuration->hasTags()->willReturn(false);
        $configuration->hasCategories()->willReturn(false);
        $configuration->hasSorting()->willReturn(false);
        $configuration->hasLimit()->willReturn(true);
        $configuration->hasPagination()->willReturn(false);
        $configuration->hasPresentAs()->willReturn(false);
        $configuration->hasAudienceTargeting()->willReturn(false);
        $configuration->getDatasourceResourceKey()->willReturn(null);
        $configuration->getDatasourceAdapter()->willReturn(null);
        $this->mediaProviderResolver->getProviderConfiguration()->willReturn($configuration->reveal());
        $this->mediaProviderResolver->getProviderDefaultParams()->willReturn([]);

        $this->tagRequestHandler->getTags('tags')->willReturn([]);
        $this->categoryRequestHandler->getCategories('categories')->willReturn([]);

        $providerResult = $this->prophesize(DataProviderResult::class);
        $providerResult->getHasNextPage()->willReturn(false);
        $providerResult->getItems()->willReturn([]);
        $this->mediaProviderResolver->resolve(
            Argument::any(),
            Argument::any(),
            Argument::any(),
            Argument::any()
        )->willReturn($providerResult->reveal());

        // Create field with an option that has non-string name (will be skipped)
        $fieldMetadata = new FieldMetadata('smart_content');

        $providerOption = new OptionMetadata();
        $providerOption->setName('provider');
        $providerOption->setValue('media');
        $fieldMetadata->addOption($providerOption);

        // Option with numeric name (should be skipped in convertOptionsToParams)
        $numericOption = new OptionMetadata();
        $numericOption->setName(123);
        $numericOption->setValue('some_value');
        $fieldMetadata->addOption($numericOption);

        $result = $this->smartContentResolver->resolve([], $fieldMetadata, 'en', []);

        $this->assertInstanceOf(ContentView::class, $result);
    }

    public function testResolvePaginatedWithZeroPage(): void
    {
        $configuration = $this->prophesize(ProviderConfigurationInterface::class);
        $configuration->getSorting()->willReturn(null);
        $configuration->hasDatasource()->willReturn(false);
        $configuration->hasTags()->willReturn(false);
        $configuration->hasCategories()->willReturn(false);
        $configuration->hasSorting()->willReturn(false);
        $configuration->hasLimit()->willReturn(true);
        $configuration->hasPagination()->willReturn(true);
        $configuration->hasPresentAs()->willReturn(false);
        $configuration->hasAudienceTargeting()->willReturn(false);
        $configuration->getDatasourceResourceKey()->willReturn(null);
        $configuration->getDatasourceAdapter()->willReturn(null);
        $this->mediaProviderResolver->getProviderConfiguration()->willReturn($configuration->reveal());
        $this->mediaProviderResolver->getProviderDefaultParams()->willReturn([]);

        $this->tagRequestHandler->getTags('tags')->willReturn([]);
        $this->categoryRequestHandler->getCategories('categories')->willReturn([]);

        $this->requestStack->getCurrentRequest()->willReturn(new Request(['p' => 0]));

        $providerResult = $this->prophesize(DataProviderResult::class);
        $providerResult->getHasNextPage()->willReturn(false);
        $providerResult->getItems()->willReturn([]);
        $this->mediaProviderResolver->resolve(
            Argument::any(),
            Argument::any(),
            Argument::any(),
            Argument::any(),
            1,
            5
        )->willReturn($providerResult->reveal());

        $result = $this->smartContentResolver->resolve([], $this->fieldMetadata, 'en', []);

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame(1, $result->getView()['page']);
    }

    public function testResolvePaginatedWithNegativePage(): void
    {
        $configuration = $this->prophesize(ProviderConfigurationInterface::class);
        $configuration->getSorting()->willReturn(null);
        $configuration->hasDatasource()->willReturn(false);
        $configuration->hasTags()->willReturn(false);
        $configuration->hasCategories()->willReturn(false);
        $configuration->hasSorting()->willReturn(false);
        $configuration->hasLimit()->willReturn(true);
        $configuration->hasPagination()->willReturn(true);
        $configuration->hasPresentAs()->willReturn(false);
        $configuration->hasAudienceTargeting()->willReturn(false);
        $configuration->getDatasourceResourceKey()->willReturn(null);
        $configuration->getDatasourceAdapter()->willReturn(null);
        $this->mediaProviderResolver->getProviderConfiguration()->willReturn($configuration->reveal());
        $this->mediaProviderResolver->getProviderDefaultParams()->willReturn([]);

        $this->tagRequestHandler->getTags('tags')->willReturn([]);
        $this->categoryRequestHandler->getCategories('categories')->willReturn([]);

        $this->requestStack->getCurrentRequest()->willReturn(new Request(['p' => -5]));

        $providerResult = $this->prophesize(DataProviderResult::class);
        $providerResult->getHasNextPage()->willReturn(false);
        $providerResult->getItems()->willReturn([]);
        $this->mediaProviderResolver->resolve(
            Argument::any(),
            Argument::any(),
            Argument::any(),
            Argument::any(),
            1,
            5
        )->willReturn($providerResult->reveal());

        $result = $this->smartContentResolver->resolve([], $this->fieldMetadata, 'en', []);

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame(1, $result->getView()['page']);
    }

    public function testResolveWithIntegerOptionValue(): void
    {
        $configuration = $this->prophesize(ProviderConfigurationInterface::class);
        $configuration->getSorting()->willReturn(null);
        $configuration->hasDatasource()->willReturn(false);
        $configuration->hasTags()->willReturn(false);
        $configuration->hasCategories()->willReturn(false);
        $configuration->hasSorting()->willReturn(false);
        $configuration->hasLimit()->willReturn(true);
        $configuration->hasPagination()->willReturn(false);
        $configuration->hasPresentAs()->willReturn(false);
        $configuration->hasAudienceTargeting()->willReturn(false);
        $configuration->getDatasourceResourceKey()->willReturn(null);
        $configuration->getDatasourceAdapter()->willReturn(null);
        $this->mediaProviderResolver->getProviderConfiguration()->willReturn($configuration->reveal());
        $this->mediaProviderResolver->getProviderDefaultParams()->willReturn([]);

        $this->tagRequestHandler->getTags('tags')->willReturn([]);
        $this->categoryRequestHandler->getCategories('categories')->willReturn([]);

        $providerResult = $this->prophesize(DataProviderResult::class);
        $providerResult->getHasNextPage()->willReturn(false);
        $providerResult->getItems()->willReturn([]);
        $this->mediaProviderResolver->resolve(
            Argument::any(),
            Argument::any(),
            Argument::any(),
            Argument::any()
        )->willReturn($providerResult->reveal());

        // Create field with an integer option value
        $fieldMetadata = new FieldMetadata('smart_content');

        $providerOption = new OptionMetadata();
        $providerOption->setName('provider');
        $providerOption->setValue('media');
        $fieldMetadata->addOption($providerOption);

        $intOption = new OptionMetadata();
        $intOption->setName('limit');
        $intOption->setValue(10);
        $fieldMetadata->addOption($intOption);

        $result = $this->smartContentResolver->resolve([], $fieldMetadata, 'en', []);

        $this->assertInstanceOf(ContentView::class, $result);
    }
}
