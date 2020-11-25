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
use Sulu\Bundle\MarkupBundle\Markup\MarkupParserInterface;
use Sulu\Component\Content\Compat\PropertyInterface;

class TextEditorResolver implements ContentTypeResolverInterface
{
    public static function getContentType(): string
    {
        return 'text_editor';
    }

    /**
     * @var MarkupParserInterface
     */
    private $markupParser;

    public function __construct(
        MarkupParserInterface $markupParser
    ) {
        $this->markupParser = $markupParser;
    }

    public function resolve($data, PropertyInterface $property, string $locale, array $attributes = []): ContentView
    {
        if (null === $data) {
            return new ContentView(null);
        }

        return new ContentView($this->markupParser->parse($data, $locale));
    }
}
