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

use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Massive\Bundle\SearchBundle\Search\SearchManagerInterface;
use Sulu\Component\Rest\ListBuilder\CollectionRepresentation;
use Sulu\Component\Rest\RequestParametersTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SearchController
{
    use RequestParametersTrait;

    /**
     * @var SearchManagerInterface
     */
    private $searchManager;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    public function __construct(SearchManagerInterface $searchManager, SerializerInterface $serializer)
    {
        $this->searchManager = $searchManager;
        $this->serializer = $serializer;
    }

    public function getAction(Request $request): Response
    {
        $query = $this->getRequestParameter($request, 'q', true);

        $locale = $request->getLocale();
        $indices = \array_filter(\explode(',', $this->getRequestParameter($request, 'indices', true, '')));

        $hits = $this->searchManager
            ->createSearch($this->prepareQuery($query))
            ->locale($locale)
            ->indexes($indices)
            ->execute();

        return new Response(
            $this->serializer->serialize(
                new CollectionRepresentation($hits, 'hits'),
                'json',
                (new SerializationContext())->setSerializeNull(true)
            ),
            200,
            [
                'Content-Type' => 'application/json',
            ]
        );
    }

    protected function prepareQuery(string $query): string
    {
        if (\strlen($query) < 3) {
            return '+("' . self::escapeDoubleQuotes($query) . '") ';
        }

        $queryString = '';
        $queryValues = \explode(' ', $query);
        foreach ($queryValues as $queryValue) {
            if (\strlen($queryValue) > 2) {
                $queryString .= '+("' . self::escapeDoubleQuotes($queryValue) . '" OR ' .
                    \preg_replace('/([^\pL\s\d])/u', '?', $queryValue) . '* OR ' .
                    \preg_replace('/([^\pL\s\d])/u', '', $queryValue) . '~) ';
            } else {
                $queryString .= '+("' . self::escapeDoubleQuotes($queryValue) . '") ';
            }
        }

        return $queryString;
    }

    protected function escapeDoubleQuotes(string $query): string
    {
        return \str_replace('"', '\\"', $query);
    }
}
