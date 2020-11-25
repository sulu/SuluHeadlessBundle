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
use Sulu\Component\Content\Compat\PropertyInterface;
use Sulu\Component\Content\Compat\PropertyParameter;
use Sulu\Component\Content\Mapper\ContentMapperInterface;
use Sulu\Component\Content\Query\ContentQueryBuilderInterface;

class PageSelectionResolver implements ContentTypeResolverInterface
{
    public static function getContentType(): string
    {
        return 'page_selection';
    }

    /**
     * @var StructureResolverInterface
     */
    private $structureResolver;

    /**
     * @var ContentQueryBuilderInterface
     */
    private $contentQueryBuilder;

    /**
     * @var ContentMapperInterface
     */
    private $contentMapper;

    /**
     * @var bool
     */
    private $showDrafts;

    public function __construct(
        StructureResolverInterface $structureResolver,
        ContentQueryBuilderInterface $contentQueryBuilder,
        ContentMapperInterface $contentMapper,
        bool $showDrafts
    ) {
        $this->structureResolver = $structureResolver;
        $this->contentQueryBuilder = $contentQueryBuilder;
        $this->contentMapper = $contentMapper;
        $this->showDrafts = $showDrafts;
    }

    public function resolve($data, PropertyInterface $property, string $locale, array $attributes = []): ContentView
    {
        if (empty($data)) {
            return new ContentView([]);
        }

        /** @var PropertyParameter[] $params */
        $params = $property->getParams();
        /** @var PropertyParameter[] $propertiesParamValue */
        $propertiesParamValue = isset($params['properties']) ? $params['properties']->getValue() : [];

        $this->contentQueryBuilder->init([
            'ids' => $data,
            'properties' => $propertiesParamValue,
            'published' => !$this->showDrafts,
        ]);
        list($pagesQuery) = $this->contentQueryBuilder->build($property->getStructure()->getWebspaceKey(), [$locale]);

        $pageStructures = $this->contentMapper->loadBySql2(
            $pagesQuery,
            $locale,
            $property->getStructure()->getWebspaceKey()
        );

        $propertyMap = [
            'title' => 'title',
            'url' => 'url',
        ];

        foreach ($propertiesParamValue as $propertiesParamEntry) {
            /** @var string $propertyValue */
            $propertyValue = $propertiesParamEntry->getValue();
            $propertyMap[$propertiesParamEntry->getName()] = $propertyValue;
        }

        $pages = [];
        foreach ($pageStructures as $pageStructure) {
            $pages[] = $this->structureResolver->resolveProperties($pageStructure, $propertyMap, $locale);
        }

        return new ContentView($pages, $data);
    }
}
