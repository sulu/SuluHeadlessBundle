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
use Sulu\Bundle\SnippetBundle\Snippet\WrongSnippetTypeException;
use Sulu\Component\Content\Compat\PropertyInterface;
use Sulu\Component\Content\Compat\Structure\SnippetBridge;
use Sulu\Component\Content\Compat\Structure\StructureBridge;
use Sulu\Component\Content\Mapper\ContentMapperInterface;

class SingleSnippetSelectionResolver implements ContentTypeResolverInterface
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
        return 'single_snippet_selection';
    }

    /**
     * {@inheritdoc}
     */
    public function resolve($data, PropertyInterface $property, string $locale, array $attributes = []): ContentView
    {
        /** @var StructureBridge $structure */
        $structure = $property->getStructure();
        $webspaceKey = $structure->getWebspaceKey();
        $shadowLocale = $structure->getIsShadow() ? $structure->getShadowBaseLanguage() : null;

        $params = $property->getParams();
        /** @var bool $loadExcerpt */
        $loadExcerpt = isset($params['loadExcerpt']) ? $params['loadExcerpt']->getValue() : false;
        /** @var string $defaultArea */
        $defaultArea = isset($params['default']) ? $params['default']->getValue() : null;

        $snippetId = $data ?? null;
        if (empty($snippetId) && $defaultArea) {
            $defaultSnippetId = $this->getDefaultSnippetId($webspaceKey, $defaultArea, $locale);
            $snippetId = $defaultSnippetId ?: null;
        }

        if (empty($snippetId)) {
            return new ContentView(null, ['id' => null]);
        }

        /** @var SnippetBridge $snippet */
        $snippet = $this->contentMapper->load($snippetId, $webspaceKey, $locale);

        if (!$snippet->getHasTranslation() && null !== $shadowLocale) {
            /** @var SnippetBridge $snippet */
            $snippet = $this->contentMapper->load($snippetId, $webspaceKey, $shadowLocale);
        }

        $snippet->setIsShadow(null !== $shadowLocale);
        $snippet->setShadowBaseLanguage($shadowLocale);

        $resolvedSnippet = $this->structureResolver->resolve($snippet, $locale, $loadExcerpt);

        return new ContentView($resolvedSnippet, ['id' => $data]);
    }

    private function getDefaultSnippetId(string $webspaceKey, string $snippetArea, string $locale): ?string
    {
        try {
            /** @var SnippetDocument|null $snippet */
            $snippet = $this->defaultSnippetManager->load($webspaceKey, $snippetArea, $locale);
        } catch (WrongSnippetTypeException $exception) {
            return null;
        }

        if (!$snippet) {
            return null;
        }

        return $snippet->getUuid();
    }
}
