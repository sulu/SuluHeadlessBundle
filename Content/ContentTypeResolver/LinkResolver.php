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
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\MarkupBundle\Markup\Link\LinkProviderPoolInterface;

class LinkResolver implements ContentTypeResolverInterface
{
    public static function getContentType(): string
    {
        return 'link';
    }

    public function __construct(
        private LinkProviderPoolInterface $linkProviderPool,
    ) {
    }

    public function resolve(mixed $data, FieldMetadata $fieldMetadata, string $locale, array $attributes = []): ContentView
    {
        $content = $this->getContentData($data, $locale);
        $view = $this->getViewData($data);

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
    private function getViewData(mixed $value): array
    {
        if (!\is_array($value) || empty($value)) {
            return [];
        }

        $result = [
            'provider' => $value['provider'] ?? null,
            'locale' => $value['locale'] ?? null,
        ];

        if (isset($value['target'])) {
            $result['target'] = $value['target'];
        }

        if (isset($value['title'])) {
            $result['title'] = $value['title'];
        }

        return $result;
    }

    private function getContentData(mixed $value, string $locale): ?string
    {
        if (!\is_array($value) || !isset($value['provider'])) {
            return null;
        }

        if (!isset($value['href'])) {
            return null;
        }

        $provider = $this->linkProviderPool->getProvider($value['provider']);

        $linkItems = \iterator_to_array($provider->preload([$value['href']], $locale));

        if (0 === \count($linkItems)) {
            return null;
        }

        $firstItem = \reset($linkItems);
        $url = $firstItem->getUrl();
        if (isset($value['anchor'])) {
            $url = \sprintf('%s#%s', $url, $value['anchor']);
        }

        return $url;
    }
}
