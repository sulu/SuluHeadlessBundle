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

use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\TeaserSerializerInterface;
use Sulu\Bundle\PageBundle\Teaser\Teaser;
use Sulu\Bundle\PageBundle\Teaser\TeaserContentType;
use Sulu\Bundle\PageBundle\Teaser\TeaserManagerInterface;
use Sulu\Component\Content\Compat\PropertyInterface;

class TeaserSelectionResolver implements ContentTypeResolverInterface
{
    public static function getContentType(): string
    {
        return 'teaser_selection';
    }

    /**
     * @var TeaserManagerInterface
     */
    private $teaserManager;

    /**
     * @var TeaserSerializerInterface
     */
    private $teaserSerializer;

    public function __construct(TeaserManagerInterface $teaserManager, TeaserSerializerInterface $teaserSerializer)
    {
        $this->teaserManager = $teaserManager;
        $this->teaserSerializer = $teaserSerializer;
    }

    public function resolve($data, PropertyInterface $property, string $locale, array $attributes = []): ContentView
    {
        $value = $this->getValue($property);
        $items = $this->getItems($property);

        if (empty($items)) {
            return new ContentView([], $value);
        }

        $teasers = $this->teaserManager->find($items, $locale);
        $teasers = array_map(function (Teaser $teaser) use ($locale) {
            return $this->teaserSerializer->serialize($teaser, $locale);
        }, $teasers);

        return new ContentView($teasers, $value);
    }

    /**
     * @see TeaserContentType::getValue()
     *
     * @return array<string, mixed>
     */
    private function getValue(PropertyInterface $property): array
    {
        $default = ['presentAs' => null, 'items' => []];
        if (!\is_array($property->getValue())) {
            return $default;
        }

        return array_merge($default, $property->getValue());
    }

    /**
     * @see TeaserContentType::getItems()
     *
     * @return mixed[]
     */
    private function getItems(PropertyInterface $property): array
    {
        $value = $this->getValue($property);
        if (!\is_array($value['items']) || 0 === \count($value['items'])) {
            return [];
        }

        return $value['items'];
    }
}
