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

namespace Sulu\Bundle\HeadlessBundle\Tests\Traits;

use SplFileInfo;
use Sulu\Bundle\MediaBundle\Entity\Collection;
use Sulu\Bundle\MediaBundle\Entity\CollectionInterface;
use Sulu\Bundle\MediaBundle\Entity\CollectionMeta;
use Sulu\Bundle\MediaBundle\Entity\CollectionType;
use Sulu\Bundle\MediaBundle\Entity\File;
use Sulu\Bundle\MediaBundle\Entity\FileVersion;
use Sulu\Bundle\MediaBundle\Entity\FileVersionMeta;
use Sulu\Bundle\MediaBundle\Entity\Media;
use Sulu\Bundle\MediaBundle\Entity\MediaInterface;
use Sulu\Bundle\MediaBundle\Entity\MediaType;
use Symfony\Component\HttpFoundation\File\UploadedFile;

trait CreateMediaTrait
{
    private static function createCollection(string $title, string $locale): CollectionInterface
    {
        $manager = static::$container->get('doctrine.orm.entity_manager');

        $collection = new Collection();
        $collectionType = $manager->getRepository(CollectionType::class)->find(1);

        if (!$collectionType) {
            throw new \RuntimeException('CollectionType "1" not found. Maybe sulu fixtures missing?');
        }

        $collection->setType($collectionType);
        $meta = new CollectionMeta();
        $meta->setLocale($locale);
        $meta->setTitle($title);
        $meta->setCollection($collection);

        $collection->addMeta($meta);
        $collection->setDefaultMeta($meta);

        $manager->persist($collection);
        $manager->persist($meta);

        return $collection;
    }

    private static function createMedia(
        string $title,
        CollectionInterface $collection,
        string $locale
    ): MediaInterface {
        $manager = static::$container->get('doctrine.orm.entity_manager');

        $file = new SplFileInfo(__DIR__ . \DIRECTORY_SEPARATOR . 'test-image.png');
        $fileName = $file->getFilename();
        $uploadedFile = new UploadedFile($file->getPathname(), $fileName);

        $storageOptions = static::$container->get('sulu_media.storage')->save(
            $uploadedFile->getPathname(),
            $fileName
        );

        $mediaType = $manager->getRepository(MediaType::class)->find(2);

        if (!$mediaType instanceof MediaType) {
            throw new \RuntimeException('MediaType "2" not found. Maybe sulu fixtures missing?');
        }

        $media = new Media();

        $file = new File();
        $file->setVersion(1)
            ->setMedia($media);

        $media->addFile($file)
            ->setType($mediaType)
            ->setCollection($collection);

        $fileVersion = new FileVersion();
        $fileVersion->setVersion($file->getVersion())
            ->setSize($uploadedFile->getSize())
            ->setName($fileName)
            ->setStorageOptions($storageOptions)
            ->setMimeType($uploadedFile->getMimeType() ?: 'image/jpeg')
            ->setFile($file);

        $file->addFileVersion($fileVersion);

        $fileVersionMeta = new FileVersionMeta();
        $fileVersionMeta->setTitle($title)
            ->setDescription('')
            ->setLocale($locale)
            ->setFileVersion($fileVersion);

        $fileVersion->addMeta($fileVersionMeta)
            ->setDefaultMeta($fileVersionMeta);

        $manager->persist($fileVersionMeta);
        $manager->persist($fileVersion);
        $manager->persist($media);

        return $media;
    }
}
