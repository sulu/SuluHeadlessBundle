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
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\CollectionSelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\SingleCollectionSelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;

class SingleCollectionSelectionResolverTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var SingleCollectionSelectionResolver
     */
    private $singleCollectionSelectionResolver;

    /**
     * @var CollectionSelectionResolver|ObjectProphecy
     */
    private $collectionSelectionResolver;

    private FieldMetadata $fieldMetadata;

    protected function setUp(): void
    {
        $this->collectionSelectionResolver = $this->prophesize(CollectionSelectionResolver::class);
        $this->fieldMetadata = new FieldMetadata('collection');

        $this->singleCollectionSelectionResolver = new SingleCollectionSelectionResolver(
            $this->collectionSelectionResolver->reveal()
        );
    }

    public function testGetContentType(): void
    {
        self::assertSame('single_collection_selection', $this->singleCollectionSelectionResolver::getContentType());
    }

    public function testResolve(): void
    {
        $this->collectionSelectionResolver->resolve([1], $this->fieldMetadata, 'en', [])->willReturn(
            new ContentView(
                [
                    [
                        'id' => 1,
                        'key' => 'key-1',
                        'title' => 'title-1',
                        'description' => 'description-1',
                    ],
                ],
                ['ids' => [1]],
            )
        );

        $result = $this->singleCollectionSelectionResolver->resolve(1, $this->fieldMetadata, 'en');

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame(
            [
                'id' => 1,
                'key' => 'key-1',
                'title' => 'title-1',
                'description' => 'description-1',
            ],
            $result->getContent()
        );
        $this->assertSame(
            ['id' => 1],
            $result->getView()
        );
    }

    public function testResolveDataIsNull(): void
    {
        $locale = 'en';

        $result = $this->singleCollectionSelectionResolver->resolve(null, $this->fieldMetadata, $locale);

        $this->assertNull($result->getContent());

        $this->assertSame(['id' => null], $result->getView());
    }
}
