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
        $value = \array_merge(
            [
                'presentAs' => null,
                'items' => [],
            ],
            \is_array($data) ? $data : []
        );
        $items = $value['items'] ?? [];

        if (!\is_array($items) || 0 === \count($items)) {
            return new ContentView([], $value);
        }

        $teasers = $this->teaserManager->find($items, $locale);
        $teasers = \array_map(
            function (Teaser $teaser) use ($locale) {
                return $this->teaserSerializer->serialize($teaser, $locale);
            },
            $teasers
        );

        return new ContentView($teasers, $value);
    }
}
