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
use Sulu\Bundle\HeadlessBundle\Content\StructureResolverInterface;
use Sulu\Bundle\SnippetBundle\Document\SnippetDocument;
use Sulu\Bundle\SnippetBundle\Snippet\DefaultSnippetManagerInterface;
use Sulu\Component\Content\Compat\PropertyInterface;
use Sulu\Component\Content\Compat\Structure\SnippetBridge;
use Sulu\Component\Content\Compat\Structure\StructureBridge;
use Sulu\Component\Content\Mapper\ContentMapperInterface;
use Sulu\Component\DocumentManager\Exception\DocumentNotFoundException;

class SnippetSelectionResolver implements ContentTypeResolverInterface
{
    /**
     * @var StructureResolverInterface
     */
    private $structureResolver;

    /**
     * @var ContentMapperInterface
     */
    private $contentMapper;

    /**
     * @var DefaultSnippetManagerInterface
     */
    private $defaultSnippetManager;

    public function __construct(
        ContentMapperInterface $contentMapper,
        StructureResolverInterface $structureResolver,
        DefaultSnippetManagerInterface $defaultSnippetManager
    ) {
        $this->contentMapper = $contentMapper;
        $this->structureResolver = $structureResolver;
        $this->defaultSnippetManager = $defaultSnippetManager;
    }

    public static function getContentType(): string
    {
        return 'snippet_selection';
    }

    public function resolve($data, PropertyInterface $property, string $locale, array $attributes = []): ContentView
    {
        /** @var StructureBridge $structure */
        $structure = $property->getStructure();
        $webspaceKey = $structure->getWebspaceKey();
        $shadowLocale = $structure->getIsShadow() ? $structure->getShadowBaseLanguage() : null;

        $params = $property->getParams();
        /** @var bool $includeExtension */
        $includeExtension = isset($params['loadExcerpt']) ? $params['loadExcerpt']->getValue() : false;
        /** @var string $defaultArea */
        $defaultArea = isset($params['default']) ? $params['default']->getValue() : null;

        $snippetIds = $data ?? [];
        if (empty($snippetIds) && $defaultArea) {
            $defaultSnippetId = $this->defaultSnippetManager->loadIdentifier($webspaceKey, $defaultArea);
            $snippetIds = $defaultSnippetId ? [$defaultSnippetId] : [];
        }

        $snippets = [];
        foreach ($snippetIds as $snippetId) {
            try {
                /** @var SnippetBridge $snippet */
                $snippet = $this->contentMapper->load($snippetId, $webspaceKey, $locale);
            } catch (DocumentNotFoundException $e) {
                continue;
            }

            if (!$snippet->getHasTranslation() && null !== $shadowLocale) {
                /** @var SnippetBridge $snippet */
                $snippet = $this->contentMapper->load($snippetId, $webspaceKey, $shadowLocale);
                /** @var SnippetDocument $document */
                $document = $snippet->getDocument();
                $document->setLocale($shadowLocale);
                $document->setOriginalLocale($locale);
            }

            $snippet->setIsShadow(null !== $shadowLocale);
            /** @var string $shadowBaseLanguage */
            $shadowBaseLanguage = $shadowLocale;
            $snippet->setShadowBaseLanguage($shadowBaseLanguage);

            $snippets[] = $this->structureResolver->resolve($snippet, $locale, $includeExtension);
        }

        return new ContentView($snippets, ['ids' => $snippetIds ?: []]);
    }
}
