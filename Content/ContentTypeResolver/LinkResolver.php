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
use Sulu\Bundle\MarkupBundle\Markup\Link\LinkProviderPoolInterface;
use Sulu\Component\Content\Compat\PropertyInterface;
use Sulu\Component\Content\Types\Link;

class LinkResolver implements ContentTypeResolverInterface
{
    /**
     * @var LinkProviderPoolInterface
     */
    private $linkProviderPool;

    public static function getContentType(): string
    {
        return 'link';
    }

    public function __construct(
        LinkProviderPoolInterface $linkProviderPool
    ) {
        $this->linkProviderPool = $linkProviderPool;
    }

    public function resolve($data, PropertyInterface $property, string $locale, array $attributes = []): ContentView
    {
        $content = $this->getContentData($property);
        $view = $this->getViewData($property);

        return new ContentView($content, $view);
    }

    /**
     * @return array{
     *     provider?: string,
     *     locale?: string,
     *     target?: string,
     *     title?: string
     * }
     */
    private function getViewData(PropertyInterface $property): array
    {
        $value = $property->getValue();

        if (!$value) {
            return [];
        }

        $result = [
            'provider' => $value['provider'],
            'locale' => $value['locale'],
        ];

        if (isset($value['target'])) {
            $result['target'] = $value['target'];
        }

        if (isset($value['title'])) {
            $result['title'] = $value['title'];
        }

        return $result;
    }

    private function getContentData(PropertyInterface $property): ?string
    {
        $value = $property->getValue();

        if (!$value || !isset($value['provider'])) {
            return null;
        }

        if (Link::LINK_TYPE_EXTERNAL === $value['provider']) {
            return $value['href'];
        }

        $provider = $this->linkProviderPool->getProvider($value['provider']);

        $linkItems = $provider->preload([$value['href']], $value['locale']);

        if (0 === \count($linkItems)) {
            return null;
        }

        $url = reset($linkItems)->getUrl();
        if (isset($value['anchor'])) {
            $url = sprintf('%s#%s', $url, $value['anchor']);
        }

        return $url;
    }
}
