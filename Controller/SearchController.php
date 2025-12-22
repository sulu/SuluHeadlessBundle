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

namespace Sulu\Bundle\HeadlessBundle\Controller;

use CmsIg\Seal\EngineInterface;
use CmsIg\Seal\Search\Condition\Condition;
use Sulu\Component\Rest\RequestParametersTrait;
use Sulu\Component\Webspace\Analyzer\Attributes\RequestAttributes;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SearchController
{
    use RequestParametersTrait;

    public function __construct(
        private EngineInterface $engine,
    ) {
    }

    public function getAction(Request $request): Response
    {
        $query = $this->getRequestParameter($request, 'q', true);
        $locale = $request->getLocale();

        /** @var RequestAttributes $attributes */
        $attributes = $request->attributes->get('_sulu');
        $webspace = $attributes->getAttribute('webspace');
        $webspaceKey = $webspace?->getKey();

        $indexName = $this->getRequestParameter($request, 'index', false, 'website');

        $hits = [];

        if ($query) {
            $search = $this->engine->createSearchBuilder($indexName)
                ->addFilter(Condition::search($query));

            if ($locale) {
                $search->addFilter(Condition::equal('locale', $locale));
            }

            if ($webspaceKey) {
                $search->addFilter(Condition::equal('webspaces', $webspaceKey));
            }

            $search->highlight(['title', 'content'], '<mark>', '</mark>');

            foreach ($search->getResult() as $document) {
                $hits[] = $document;
            }
        }

        return new JsonResponse([
            'hits' => $hits,
        ]);
    }
}
