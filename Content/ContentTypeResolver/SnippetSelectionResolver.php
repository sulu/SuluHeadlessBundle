<?php


namespace Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver;


use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\StructureResolver;
use Sulu\Bundle\HeadlessBundle\Content\StructureResolverInterface;
use Sulu\Component\Content\Compat\PropertyInterface;
use Sulu\Component\Content\Mapper\ContentMapperInterface;

class SnippetSelectionResolver implements ContentTypeResolverInterface
{


    /**
     * @var array
     */
    private $snippetCache = [];

    /**
     * @var StructureResolver
     */
    private $structureResolver;
    /**
     * @var ContentMapperInterface
     */
    private $contentMapper;

    /**
     * SnippetSelectionResolver constructor.
     * @param ContentMapperInterface $contentMapper
     * @param StructureResolver $structureResolver
     */
    public function __construct(
        ContentMapperInterface $contentMapper,
        StructureResolverInterface $structureResolver)
    {
        $this->contentMapper = $contentMapper;
        $this->structureResolver = $structureResolver;
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

        $shadowLocale = null;
        if ($property->getStructure()->getIsShadow()) {
            $shadowLocale = $property->getStructure()->getShadowBaseLanguage();
        }

        $snippets = [];
        foreach ($data as $uuid) {
            if (!array_key_exists($uuid, $this->snippetCache)) {
                $snippet = $this->contentMapper->load($uuid, $property, $locale);

                if (!$snippet->getHasTranslation() && null !== $shadowLocale) {
                    $snippet = $this->contentMapper->load($uuid, $property, $shadowLocale);
                }

                $snippet->setIsShadow(null !== $shadowLocale);
                $snippet->setShadowBaseLanguage($shadowLocale);

                $resolved = $this->structureResolver->resolve($snippet, $locale);

                if ($resolved['extension']['excerpt']) {
                    $resolved['content']['taxonomies'] = [
                        'categories' => $resolved['extension']['excerpt']['categories'],
                        'tags' => $resolved['extension']['excerpt']['tags'],
                    ];
                    unset($resolved['extension']);
                }

                $resolved['view']['template'] = $snippet->getKey();
                $resolved['view']['uuid'] = $snippet->getUuid();

                $this->snippetCache[$uuid] = $resolved;
            }

            $snippets[] = $this->snippetCache[$uuid];
        }

        return new ContentView($snippets ?? null, $data);
    }
}
