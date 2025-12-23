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

namespace Sulu\Bundle\HeadlessBundle\Content\Resolver;

/**
 * Parses property paths with dot notation for extension properties.
 *
 * Examples:
 *   'title' -> context: 'template', field: 'title'
 *   'excerpt.title' -> context: 'excerpt', field: 'title'
 *   'seo.description' -> context: 'seo', field: 'description'
 */
class PropertyPathParser
{
    private const CONTEXT_TEMPLATE = 'template';
    private const VALID_CONTEXTS = ['template', 'excerpt', 'seo'];

    /**
     * Parse a property path into its components.
     *
     * @return array{context: string, field: string}
     */
    public function parse(string $propertyPath): array
    {
        if (\str_contains($propertyPath, '.')) {
            [$context, $field] = \explode('.', $propertyPath, 2);

            // Validate context
            if (!\in_array($context, self::VALID_CONTEXTS, true)) {
                // Invalid context - treat as template field
                return ['context' => self::CONTEXT_TEMPLATE, 'field' => $propertyPath];
            }

            return ['context' => $context, 'field' => $field];
        }

        return ['context' => self::CONTEXT_TEMPLATE, 'field' => $propertyPath];
    }

    /**
     * Check if a property path matches a specific context.
     */
    public function matchesContext(string $propertyPath, string $context): bool
    {
        $parsed = $this->parse($propertyPath);

        return $parsed['context'] === $context;
    }

    /**
     * Group property paths by their context.
     *
     * @param array<string, string> $propertyMap Map of target => source paths
     *
     * @return array<string, array<string, string>> Grouped by context
     */
    public function groupByContext(array $propertyMap): array
    {
        $grouped = [];

        foreach ($propertyMap as $targetKey => $sourcePath) {
            $parsed = $this->parse($sourcePath);
            $context = $parsed['context'];

            if (!isset($grouped[$context])) {
                $grouped[$context] = [];
            }

            $grouped[$context][$targetKey] = $parsed['field'];
        }

        return $grouped;
    }
}
