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

namespace Sulu\Bundle\HeadlessBundle\Tests\Functional\Content;

use Sulu\Bundle\HeadlessBundle\Content\StructureResolverInterface;
use Sulu\Bundle\HeadlessBundle\Tests\Functional\BaseTestCase;
use Sulu\Bundle\HeadlessBundle\Tests\Traits\CreateMediaTrait;
use Sulu\Bundle\HeadlessBundle\Tests\Traits\CreatePageTrait;
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Page\Domain\Model\Page;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class StructureResolverPreviewTest extends BaseTestCase
{
    use CreateMediaTrait;
    use CreatePageTrait;

    private static Page $page;

    public static function setUpBeforeClass(): void
    {
        static::purgeDatabase();
        self::bootKernel();

        $collection = self::createCollection('Test Collection', 'de');
        $media = self::createMedia('Test Image', $collection, 'de');
        self::getEntityManager()->flush();

        self::$page = self::createPage([
            'title' => 'Block Ids',
            'url' => '/block-ids',
            'template' => 'resolver-test',
            'blocks' => [
                [
                    'type' => 'text',
                    'title' => 'Block Title',
                    '_id' => 'block-1',
                ],
            ],
            'image_map' => [
                'imageId' => $media->getId(),
                'hotspots' => [
                    [
                        'type' => 'basic',
                        'hotspot' => ['type' => 'point', 'left' => 0.1, 'top' => 0.2],
                        'title' => 'Hotspot Title',
                        '_id' => 'hotspot-1',
                    ],
                ],
            ],
        ]);

        self::getEntityManager()->clear();

        static::ensureKernelShutdown();
    }

    public function testResolveExposesIdsDuringPreview(): void
    {
        $content = $this->resolveContent(new Request([], [], ['preview' => true]));

        $this->assertSame('block-1', $content['blocks'][0]['_id']);
        $this->assertSame('hotspot-1', $content['image_map']['hotspots'][0]['_id']);
    }

    public function testResolveOmitsIdsOutsidePreview(): void
    {
        $content = $this->resolveContent(new Request());

        $this->assertArrayNotHasKey('_id', $content['blocks'][0]);
        $this->assertArrayNotHasKey('_id', $content['image_map']['hotspots'][0]);
    }

    /**
     * @return array{
     *     blocks: array<int, array<string, mixed>>,
     *     image_map: array{hotspots: array<int, array<string, mixed>>},
     * }
     */
    private function resolveContent(Request $request): array
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var RequestStack $requestStack */
        $requestStack = $container->get('request_stack');
        $requestStack->push($request);

        /** @var ContentAggregatorInterface $contentAggregator */
        $contentAggregator = $container->get(ContentAggregatorInterface::class);
        $dimensionContent = $contentAggregator->aggregate(
            self::$page,
            ['locale' => 'de', 'stage' => DimensionContentInterface::STAGE_LIVE],
        );

        /** @var StructureResolverInterface $structureResolver */
        $structureResolver = $container->get('sulu_headless.structure_resolver');

        /** @var array{
         *     content: array{
         *         blocks: array<int, array<string, mixed>>,
         *         image_map: array{hotspots: array<int, array<string, mixed>>},
         *     },
         * } $result
         */
        $result = $structureResolver->resolve($dimensionContent, 'de');

        return $result['content'];
    }
}
