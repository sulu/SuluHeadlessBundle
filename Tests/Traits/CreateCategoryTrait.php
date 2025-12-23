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

use Sulu\Bundle\CategoryBundle\Entity\Category;
use Sulu\Bundle\CategoryBundle\Entity\CategoryInterface;
use Sulu\Bundle\CategoryBundle\Entity\CategoryTranslation;

trait CreateCategoryTrait
{
    /**
     * @param array{name: string, key?: string, description?: string} $data
     */
    private static function createCategory(
        array $data,
        string $locale = 'de',
        ?CategoryInterface $parent = null
    ): CategoryInterface {
        $manager = static::getContainer()->get('doctrine.orm.entity_manager');

        $category = new Category();
        $category->setDefaultLocale($locale);

        if ($parent) {
            $category->setParent($parent);
        }

        if (isset($data['key'])) {
            $category->setKey($data['key']);
        }

        $translation = new CategoryTranslation();
        $translation->setCategory($category);
        $translation->setLocale($locale);
        $translation->setTranslation($data['name']);

        if (isset($data['description'])) {
            $translation->setDescription($data['description']);
        }

        $category->addTranslation($translation);

        $manager->persist($category);
        $manager->persist($translation);

        return $category;
    }
}
