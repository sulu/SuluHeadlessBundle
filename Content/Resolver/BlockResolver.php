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

use Sulu\Bundle\HeadlessBundle\Content\ContentResolverInterface;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Component\Content\Compat\Block\BlockPropertyInterface;
use Sulu\Component\Content\Compat\PropertyInterface;

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

    public function __construct(ContentResolverInterface $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * @param BlockPropertyInterface $property
     */
    public function resolve($data, PropertyInterface $property, string $locale, array $attributes = []): ContentView
    {
        $content = [];
        $view = [];
        for ($i = 0; $i < $property->getLength(); ++$i) {
            $blockPropertyType = $property->getProperties($i);

            $content[$i] = ['type' => $blockPropertyType->getName()];
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
