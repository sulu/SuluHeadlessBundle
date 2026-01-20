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

namespace Sulu\Bundle\HeadlessBundle\Content\ExtensionResolver;

class ExtensionResolverProvider
{
    /**
     * @param iterable<ExtensionResolverInterface> $resolvers
     */
    public function __construct(
        private iterable $resolvers,
    ) {
    }

    /**
     * @return iterable<ExtensionResolverInterface>
     */
    public function getResolvers(): iterable
    {
        return $this->resolvers;
    }

    public function getResolverByPrefix(string $prefix): ?ExtensionResolverInterface
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->getPrefix() === $prefix) {
                return $resolver;
            }
        }

        return null;
    }

    /**
     * @param array<string, string> $properties
     */
    public function hasPropertiesWithPrefix(array $properties, string $prefix): bool
    {
        foreach ($properties as $sourceKey) {
            if (\str_starts_with($sourceKey, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
