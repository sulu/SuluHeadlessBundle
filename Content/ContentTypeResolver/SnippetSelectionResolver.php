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
use Sulu\Bundle\HeadlessBundle\Content\StructureResolverInterface;
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Snippet\Domain\Repository\SnippetAreaRepositoryInterface;
use Sulu\Snippet\Domain\Repository\SnippetRepositoryInterface;

class SnippetSelectionResolver implements ContentTypeResolverInterface
{
    public static function getContentType(): string
    {
        return 'snippet_selection';
    }

    public function __construct(
        private SnippetRepositoryInterface $snippetRepository,
        private StructureResolverInterface $structureResolver,
        private ContentAggregatorInterface $contentAggregator,
        private SnippetAreaRepositoryInterface $snippetAreaRepository,
    ) {
    }

    public function resolve(mixed $data, FieldMetadata $fieldMetadata, string $locale, array $attributes = []): ContentView
    {
        $webspaceKey = $attributes['webspaceKey'] ?? null;
        $shadowLocale = ($attributes['isShadow'] ?? false) ? ($attributes['shadowLocale'] ?? null) : null;

        // Get parameters from field options
        $includeExtension = false;
        $defaultArea = null;
        foreach ($fieldMetadata->getOptions() as $option) {
            if ('loadExcerpt' === $option->getName()) {
                $includeExtension = (bool) $option->getValue();
            }
            if ('default' === $option->getName()) {
                $defaultArea = (string) $option->getValue();
            }
        }

        $snippetIds = \is_array($data) ? $data : [];

        // Load default snippet if no snippets selected and default area is configured
        if (empty($snippetIds) && $defaultArea && $webspaceKey) {
            $snippetArea = $this->snippetAreaRepository->findOneBy([
                'webspaceKey' => $webspaceKey,
                'areaKey' => $defaultArea,
            ]);
            $defaultSnippetId = $snippetArea?->getSnippet()?->getUuid();
            $snippetIds = $defaultSnippetId ? [$defaultSnippetId] : [];
        }

        if (empty($snippetIds)) {
            return new ContentView([], ['ids' => []]);
        }

        // Load snippets using repository
        $loadLocale = $shadowLocale ?? $locale;
        $snippetEntities = $this->snippetRepository->findBy(
            [
                'uuids' => $snippetIds,
                'locale' => $loadLocale,
                'stage' => DimensionContentInterface::STAGE_LIVE,
                'load_ghost_content' => true,
            ],
            [],
            [SnippetRepositoryInterface::GROUP_SELECT_SNIPPET_WEBSITE => true],
        );

        $snippets = [];
        foreach ($snippetEntities as $snippet) {
            $dimensionContent = $this->contentAggregator->aggregate(
                $snippet,
                ['locale' => $loadLocale, 'stage' => DimensionContentInterface::STAGE_LIVE],
            );
            $snippets[] = $this->structureResolver->resolve($dimensionContent, $locale, $includeExtension);
        }

        return new ContentView($snippets, ['ids' => $snippetIds]);
    }
}
