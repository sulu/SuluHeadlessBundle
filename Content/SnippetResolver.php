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

namespace Sulu\Bundle\HeadlessBundle\Content;

use Sulu\Component\Content\Compat\Structure\SnippetBridge;
use Sulu\Component\Content\Mapper\ContentMapperInterface;

class SnippetResolver implements SnippetResolverInterface
{
    /**
     * @var ContentMapperInterface
     */
    private $contentMapper;

    /**
     * @var StructureResolverInterface
     */
    private $structureResolver;

    public function __construct(ContentMapperInterface $contentMapper, StructureResolverInterface $structureResolver)
    {
        $this->contentMapper = $contentMapper;
        $this->structureResolver = $structureResolver;
    }

    /**
     * @return mixed[]
     */
    public function resolve(
        string $uuid,
        string $webspaceKey,
        string $locale,
        ?string $shadowLocale = null,
        bool $loadExcerpt = false
    ): array {
        /** @var SnippetBridge $snippet */
        $snippet = $this->contentMapper->load($uuid, $webspaceKey, $locale);

        if (!$snippet->getHasTranslation() && null !== $shadowLocale) {
            /** @var SnippetBridge $snippet */
            $snippet = $this->contentMapper->load($uuid, $webspaceKey, $shadowLocale);
        }

        $snippet->setIsShadow(null !== $shadowLocale);
        $snippet->setShadowBaseLanguage($shadowLocale);

        $resolvedSnippet = $this->structureResolver->resolve($snippet, $locale, $loadExcerpt);

        return $resolvedSnippet;
    }
}
