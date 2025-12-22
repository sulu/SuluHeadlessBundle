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

interface ContentResolverInterface
{
    /**
     * Resolve content data using the appropriate content type resolver.
     *
     * @param mixed $data The raw data from DimensionContent->getTemplateData()
     * @param FieldMetadata $fieldMetadata The field metadata from FormMetadata
     * @param string $locale The current locale
     * @param array<string, mixed> $attributes Context attributes (webspaceKey, uuid, isShadow, etc.)
     */
    public function resolve(mixed $data, FieldMetadata $fieldMetadata, string $locale, array $attributes = []): ContentView;
}
