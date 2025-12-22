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

namespace Sulu\Bundle\HeadlessBundle\Content;

use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\ContentTypeResolverInterface;

class ContentResolver implements ContentResolverInterface
{
    /**
     * @var array<string, ContentTypeResolverInterface>
     */
    private array $resolvers;

    /**
     * @param \Traversable<ContentTypeResolverInterface> $resolvers
     */
    public function __construct(\Traversable $resolvers)
    {
        $this->resolvers = \iterator_to_array($resolvers);
    }

    public function resolve(mixed $data, FieldMetadata $fieldMetadata, string $locale, array $attributes = []): ContentView
    {
        $type = $fieldMetadata->getType();

        if (!\array_key_exists($type, $this->resolvers)) {
            return new ContentView($data);
        }

        return $this->resolvers[$type]->resolve($data, $fieldMetadata, $locale, $attributes);
    }
}
