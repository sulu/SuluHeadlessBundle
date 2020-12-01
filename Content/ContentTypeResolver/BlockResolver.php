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

use Sulu\Bundle\AudienceTargetingBundle\TargetGroup\TargetGroupStoreInterface;
use Sulu\Bundle\HeadlessBundle\Content\ContentResolverInterface;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Component\Content\Compat\Block\BlockPropertyInterface;
use Sulu\Component\Content\Compat\PropertyInterface;
use Sulu\Component\Webspace\Analyzer\RequestAnalyzerInterface;

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
     * @var RequestAnalyzerInterface
     */
    private $requestAnalyzer;

    /**
     * @var TargetGroupStoreInterface|null
     */
    private $targetGroupStore;

    public function __construct(
        ContentResolverInterface $resolver,
        RequestAnalyzerInterface $requestAnalyzer,
        TargetGroupStoreInterface $targetGroupStore = null
    )
    {
        $this->resolver = $resolver;
        $this->requestAnalyzer = $requestAnalyzer;
        $this->targetGroupStore = $targetGroupStore;
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
            $blockPropertyTypeSettings = $blockPropertyType->getSettings();

            if (
                \is_array($blockPropertyTypeSettings)
                && !empty($blockPropertyTypeSettings['hidden'])
            ) {
                continue;
            }

            if (\is_array($blockPropertyTypeSettings)) {
                $webspaceKey = $this->requestAnalyzer->getWebspace()->getKey();
                $segment = $this->requestAnalyzer->getSegment();
                if (isset($blockPropertyTypeSettings['segment_enabled'])
                    && $blockPropertyTypeSettings['segment_enabled']
                    && isset($blockPropertyTypeSettings['segments'][$webspaceKey])
                    && $segment
                    && $blockPropertyTypeSettings['segments'][$webspaceKey] !== $segment->getKey()
                ) {
                    continue;
                }

                if (isset($blockPropertyTypeSettings['target_groups_enabled'])
                    && $blockPropertyTypeSettings['target_groups_enabled']
                    && isset($blockPropertyTypeSettings['target_groups'])
                    && $this->targetGroupStore
                    && !\in_array($this->targetGroupStore->getTargetGroupId(), $blockPropertyTypeSettings['target_groups'])
                ) {
                    continue;
                }
            }

            $content[$i] = [
                'type' => $blockPropertyType->getName(),
                'settings' => $blockPropertyTypeSettings
            ];
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
