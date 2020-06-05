<?php


namespace Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver;


use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\SnippetBundle\Snippet\SnippetResolver;
use Sulu\Component\Content\Compat\PropertyInterface;

class SnippetSelectionResolver implements ContentTypeResolverInterface
{

    /**
     * @var SnippetResolver
     */
    private $snippetResolver;

    /**
     * SnippetSelectionResolver constructor.
     * @param SnippetResolver $snippetResolver
     */
    public function __construct(SnippetResolver $snippetResolver)
    {
        $this->snippetResolver = $snippetResolver;
    }

    public static function getContentType(): string
    {
        return 'snippet_selection';
    }

    /**
     * @inheritDoc
     */
    public function resolve($data, PropertyInterface $property, string $locale, array $attributes = []): ContentView
    {
        if (null === $data) {
            return new ContentView([]);
        }

        $content = $this->snippetResolver->resolve($data, $property, $locale, $attributes);

        return new ContentView($content ?? null, $data);
    }
}
