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

use PHPUnit\Framework\TestCase;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\CollectionSerializer;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\CollectionSerializerInterface;
use Sulu\Bundle\MediaBundle\Api\Collection as ApiCollection;
use Sulu\Bundle\MediaBundle\Entity\Collection;
use Sulu\Bundle\TestBundle\Testing\SetGetPrivatePropertyTrait;

class CollectionSerializerTest extends TestCase
{
    use SetGetPrivatePropertyTrait;

    /**
     * @var CollectionSerializerInterface
     */
    private $collectionSerializer;

    protected function setUp(): void
    {
        $this->collectionSerializer = new CollectionSerializer();
    }

    public function testSerialize(): void
    {
        $locale = 'en';

        $collection = new Collection();
        self::setPrivateProperty($collection, 'id', 1);

        $apiCollection = new ApiCollection($collection, $locale);
        $apiCollection->setKey('key');
        $apiCollection->setTitle('title');
        $apiCollection->setDescription('description');

        $result = $this->collectionSerializer->serialize($collection, $locale);

        $this->assertSame([
            'id' => 1,
            'key' => 'key',
            'title' => 'title',
            'description' => 'description',
        ], $result);
    }
}
