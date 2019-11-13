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

use Sulu\Bundle\PageBundle\Content\PageSelectionContainer;
use Sulu\Component\Content\Query\ContentQueryBuilderInterface;
use Sulu\Component\Content\Query\ContentQueryExecutorInterface;
use Sulu\Component\HttpKernel\SuluKernel;

/**
 * @codeCoverageIgnore
 */
class PageSelectionContainerFactory
{
    /**
     * @var ContentQueryExecutorInterface
     */
    private $contentQueryExecutor;

    /**
     * @var ContentQueryBuilderInterface
     */
    private $contentQueryBuilder;

    /**
     * @var string
     */
    private $suluContext;

    /**
     * @var ?bool
     */
    private $suluPreview;

    /**
     * @var bool
     */
    private $showDrafts;

    public function __construct(
        ContentQueryExecutorInterface $contentQueryExecutor,
        ContentQueryBuilderInterface $contentQueryBuilder,
        string $suluContext,
        ?bool $suluPreview
    ) {
        $this->contentQueryExecutor = $contentQueryExecutor;
        $this->contentQueryBuilder = $contentQueryBuilder;
        $this->suluContext = $suluContext;
        $this->suluPreview = $suluPreview;

        $this->showDrafts = SuluKernel::CONTEXT_ADMIN === $this->suluContext || $this->suluPreview;
    }

    /**
     * @param mixed[] $data
     * @param mixed[] $params
     */
    public function createContainer(
        array $data,
        array $params,
        string $webspaceKey,
        string $locale,
        ?bool $showDrafts = null
    ): PageSelectionContainer {
        $container = new PageSelectionContainer(
            $data,
            $this->contentQueryExecutor,
            $this->contentQueryBuilder,
            $params,
            $webspaceKey,
            $locale,
            isset($showDrafts) ? $showDrafts : $this->showDrafts
        );

        return $container;
    }
}
