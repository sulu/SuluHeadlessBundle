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

namespace Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver;

use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TypedFormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderRegistry;
use Sulu\Bundle\HeadlessBundle\Content\ContentResolverInterface;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Content\Application\PropertyResolver\BlockVisitor\BlockVisitorInterface;

class BlockResolver implements ContentTypeResolverInterface
{
    public static function getContentType(): string
    {
        return 'block';
    }

    /**
     * @param iterable<BlockVisitorInterface> $blockVisitors
     */
    public function __construct(
        private ContentResolverInterface $contentResolver,
        private MetadataProviderRegistry $metadataProviderRegistry,
        private iterable $blockVisitors = [],
    ) {
    }

    public function resolve(mixed $data, FieldMetadata $fieldMetadata, string $locale, array $attributes = []): ContentView
    {
        if (!\is_array($data)) {
            return new ContentView([], []);
        }

        $blockTypes = $fieldMetadata->getTypes();

        $globalBlocksMetadata = $this->getGlobalBlocksMetadata($locale);

        $content = [];
        $view = [];

        foreach ($data as $i => $blockItem) {
            if (!\is_array($blockItem) || !isset($blockItem['type'])) {
                continue;
            }

            foreach ($this->blockVisitors as $blockVisitor) {
                $blockItem = $blockVisitor->visit($blockItem);
                if (null === $blockItem) {
                    continue 2; // Skip this block if visitor returns null
                }
            }

            $blockTypeName = $blockItem['type'];
            $blockTypeMetadata = $blockTypes[$blockTypeName] ?? null;

            if (!$blockTypeMetadata) {
                continue;
            }

            $globalBlockType = $this->getGlobalBlockType($blockTypeMetadata);
            if ($globalBlockType && isset($globalBlocksMetadata[$globalBlockType])) {
                $blockTypeMetadata = $globalBlocksMetadata[$globalBlockType];
            }

            $content[$i] = [
                'type' => $blockTypeName,
                'settings' => $blockItem['settings'] ?? [],
            ];
            $view[$i] = [];

            $blockFieldMetadata = $blockTypeMetadata->getFlatFieldMetadata();

            foreach ($blockFieldMetadata as $fieldName => $childFieldMetadata) {
                $fieldValue = $blockItem[$fieldName] ?? null;
                $contentView = $this->contentResolver->resolve($fieldValue, $childFieldMetadata, $locale, $attributes);

                $content[$i][$fieldName] = $contentView->getContent();
                $view[$i][$fieldName] = $contentView->getView();
            }
        }

        return new ContentView(\array_values($content), \array_values($view));
    }

    /**
     * @return array<string, FormMetadata>
     */
    private function getGlobalBlocksMetadata(string $locale): array
    {
        $typedFormMetadata = $this->metadataProviderRegistry->getMetadataProvider('form')
            ->getMetadata('block', $locale, []);

        if (!$typedFormMetadata instanceof TypedFormMetadata) {
            return [];
        }

        return $typedFormMetadata->getForms();
    }

    private function getGlobalBlockType(FormMetadata $formMetadata): ?string
    {
        $tag = $formMetadata->getTagsByName('sulu.global_block')[0] ?? null;

        /** @var string|null $result */
        $result = $tag?->getAttribute('global_block');

        return $result;
    }
}
