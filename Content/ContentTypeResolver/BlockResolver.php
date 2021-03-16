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

use Sulu\Bundle\HeadlessBundle\Content\ContentResolverInterface;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Component\Content\Compat\Block\BlockPropertyInterface;
use Sulu\Component\Content\Compat\PropertyInterface;
use Sulu\Component\Content\Types\Block\BlockVisitorInterface;

class BlockResolver implements ContentTypeResolverInterface
{
    public static function getContentType(): string
    {
        return 'block';
    }

    /**
     * @var ContentResolverInterface
     */
    private $resolver;

    /**
     * @var \Traversable<BlockVisitorInterface>
     */
    private $blockVisitors;

    /**
     * @param \Traversable<BlockVisitorInterface> $blockVisitors
     */
    public function __construct(ContentResolverInterface $contentResolver, \Traversable $blockVisitors)
    {
        $this->resolver = $contentResolver;
        $this->blockVisitors = $blockVisitors;
    }

    /**
     * @param BlockPropertyInterface $property
     */
    public function resolve($data, PropertyInterface $property, string $locale, array $attributes = []): ContentView
    {
        $blockPropertyTypes = [];
        for ($i = 0; $i < $property->getLength(); ++$i) {
            $blockPropertyType = $property->getProperties($i);

            foreach ($this->blockVisitors as $blockVisitor) {
                $blockPropertyType = $blockVisitor->visit($blockPropertyType);

                if (!$blockPropertyType) {
                    break;
                }
            }

            if ($blockPropertyType) {
                $blockPropertyTypes[] = $blockPropertyType;
            }
        }

        $content = [];
        $view = [];
        foreach ($blockPropertyTypes as $i => $blockPropertyType) {
            $content[$i] = ['type' => $blockPropertyType->getName(), 'settings' => $blockPropertyType->getSettings()];
            $view[$i] = [];

            foreach ($blockPropertyType->getChildProperties() as $childProperty) {
                $contentView = $this->resolver->resolve($childProperty->getValue(), $childProperty, $locale, $attributes);

                $content[$i][$childProperty->getName()] = $contentView->getContent();
                $view[$i][$childProperty->getName()] = $contentView->getView();
            }
        }

        return new ContentView($content, $view);
    }
}
