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

namespace Sulu\Bundle\HeadlessBundle\Tests\Functional\Controller\Integration;

use Sulu\Bundle\HeadlessBundle\Tests\Functional\BaseTestCase;
use Sulu\Bundle\HeadlessBundle\Tests\Traits\CreateMediaTrait;
use Sulu\Bundle\HeadlessBundle\Tests\Traits\CreatePageTrait;
use Sulu\Bundle\MediaBundle\DataFixtures\ORM\LoadCollectionTypes;
use Sulu\Bundle\MediaBundle\DataFixtures\ORM\LoadMediaTypes;
use Sulu\Bundle\MediaBundle\Entity\CollectionInterface;
use Sulu\Bundle\MediaBundle\Entity\MediaInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

class MediaResolversTest extends BaseTestCase
{
    use CreateMediaTrait;
    use CreatePageTrait;

    private KernelBrowser $websiteClient;

    private static CollectionInterface $collection;
    private static MediaInterface $media1;
    private static MediaInterface $media2;

    public static function setUpBeforeClass(): void
    {
        self::initPhpcr();

        $collectionTypeFixture = new LoadCollectionTypes();
        $collectionTypeFixture->load(self::getEntityManager());
        $mediaTypeFixture = new LoadMediaTypes();
        $mediaTypeFixture->load(self::getEntityManager());

        self::$collection = self::createCollection('Test Collection', 'de');
        self::$media1 = self::createMedia('Test Image 1', self::$collection, 'de');
        self::$media2 = self::createMedia('Test Image 2', self::$collection, 'de');
        self::getEntityManager()->flush();

        self::createPage([
            'title' => 'Media Single',
            'url' => '/media-single',
            'template' => 'resolver-test',
            'media_selection' => ['ids' => [self::$media1->getId()]],
        ]);

        self::createPage([
            'title' => 'Media Multiple',
            'url' => '/media-multiple',
            'template' => 'resolver-test',
            'media_selection' => ['ids' => [self::$media1->getId(), self::$media2->getId()]],
        ]);

        self::createPage([
            'title' => 'Media Empty',
            'url' => '/media-empty',
            'template' => 'resolver-test',
            'media_selection' => ['ids' => []],
        ]);

        self::createPage([
            'title' => 'Single Media Basic',
            'url' => '/single-media-basic',
            'template' => 'resolver-test',
            'single_media_selection' => ['id' => self::$media1->getId()],
        ]);

        self::createPage([
            'title' => 'Single Media Null',
            'url' => '/single-media-null',
            'template' => 'resolver-test',
            'single_media_selection' => ['id' => null],
        ]);

        self::createPage([
            'title' => 'Collection Multiple',
            'url' => '/collection-multiple',
            'template' => 'resolver-test',
            'collection_selection' => [self::$collection->getId()],
        ]);

        self::createPage([
            'title' => 'Collection Empty',
            'url' => '/collection-empty',
            'template' => 'resolver-test',
            'collection_selection' => [],
        ]);

        self::createPage([
            'title' => 'Single Collection Basic',
            'url' => '/single-collection-basic',
            'template' => 'resolver-test',
            'single_collection_selection' => self::$collection->getId(),
        ]);

        self::createPage([
            'title' => 'Single Collection Null',
            'url' => '/single-collection-null',
            'template' => 'resolver-test',
            'single_collection_selection' => null,
        ]);

        self::createPage([
            'title' => 'Image Map Hotspots',
            'url' => '/image-map-hotspots',
            'template' => 'resolver-test',
            'image_map' => [
                'imageId' => self::$media1->getId(),
                'hotspots' => [
                    [
                        'type' => 'basic',
                        'hotspot' => [
                            'type' => 'rectangle',
                            'left' => 0.1,
                            'top' => 0.2,
                            'width' => 0.3,
                            'height' => 0.4,
                        ],
                        'title' => 'Hotspot Title',
                        'description' => 'Hotspot description',
                    ],
                ],
            ],
        ]);

        self::createPage([
            'title' => 'Image Map Empty',
            'url' => '/image-map-empty',
            'template' => 'resolver-test',
            'image_map' => null,
        ]);

        static::ensureKernelShutdown();
    }

    protected function setUp(): void
    {
        $this->websiteClient = $this->createWebsiteClient();
    }

    /**
     * @dataProvider mediaSelectionProvider
     */
    public function testMediaSelection(string $url, string $fixture): void
    {
        $this->websiteClient->request('GET', $url . '.json');

        $response = $this->websiteClient->getResponse();

        $this->assertResponseContent(
            $fixture,
            $response,
            Response::HTTP_OK
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function mediaSelectionProvider(): iterable
    {
        yield 'single media' => ['/media-single', 'media/media_selection__single.json'];
        yield 'multiple media' => ['/media-multiple', 'media/media_selection__multiple.json'];
        yield 'empty media' => ['/media-empty', 'media/media_selection__empty.json'];
    }

    /**
     * @dataProvider singleMediaSelectionProvider
     */
    public function testSingleMediaSelection(string $url, string $fixture): void
    {
        $this->websiteClient->request('GET', $url . '.json');

        $response = $this->websiteClient->getResponse();

        $this->assertResponseContent(
            $fixture,
            $response,
            Response::HTTP_OK
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function singleMediaSelectionProvider(): iterable
    {
        yield 'basic' => ['/single-media-basic', 'media/single_media_selection__basic.json'];
        yield 'null' => ['/single-media-null', 'media/single_media_selection__null.json'];
    }

    /**
     * @dataProvider collectionSelectionProvider
     */
    public function testCollectionSelection(string $url, string $fixture): void
    {
        $this->websiteClient->request('GET', $url . '.json');

        $response = $this->websiteClient->getResponse();

        $this->assertResponseContent(
            $fixture,
            $response,
            Response::HTTP_OK
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function collectionSelectionProvider(): iterable
    {
        yield 'multiple' => ['/collection-multiple', 'media/collection_selection__multiple.json'];
        yield 'empty' => ['/collection-empty', 'media/collection_selection__empty.json'];
    }

    /**
     * @dataProvider singleCollectionSelectionProvider
     */
    public function testSingleCollectionSelection(string $url, string $fixture): void
    {
        $this->websiteClient->request('GET', $url . '.json');

        $response = $this->websiteClient->getResponse();

        $this->assertResponseContent(
            $fixture,
            $response,
            Response::HTTP_OK
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function singleCollectionSelectionProvider(): iterable
    {
        yield 'basic' => ['/single-collection-basic', 'media/single_collection_selection__basic.json'];
        yield 'null' => ['/single-collection-null', 'media/single_collection_selection__null.json'];
    }

    /**
     * @dataProvider imageMapProvider
     */
    public function testImageMap(string $url, string $fixture): void
    {
        $this->websiteClient->request('GET', $url . '.json');

        $response = $this->websiteClient->getResponse();

        $this->assertResponseContent(
            $fixture,
            $response,
            Response::HTTP_OK
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function imageMapProvider(): iterable
    {
        yield 'with hotspots' => ['/image-map-hotspots', 'media/image_map__hotspots.json'];
        yield 'empty' => ['/image-map-empty', 'media/image_map__empty.json'];
    }
}
