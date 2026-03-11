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

use JMS\Serializer\SerializerInterface;
use Sulu\Bundle\HttpCacheBundle\Cache\SuluHttpCache;
use Sulu\Bundle\WebsiteBundle\Entity\Analytics;
use Sulu\Bundle\WebsiteBundle\Entity\AnalyticsRepositoryInterface;
use Sulu\Component\Rest\RequestParametersTrait;
use Sulu\Component\Webspace\Analyzer\Attributes\RequestAttributes;
use Sulu\Component\Webspace\Webspace;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AnalyticsController
{
    use RequestParametersTrait;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var AnalyticsRepositoryInterface
     */
    private $analyticsRepository;

    /**
     * @var int
     */
    private $maxAge;

    /**
     * @var int
     */
    private $sharedMaxAge;

    /**
     * @var int
     */
    private $cacheLifetime;

    /**
     * @var string
     */
    private $environment;

    public function __construct(
        SerializerInterface $serializer,
        AnalyticsRepositoryInterface $analyticsRepository,
        string $environment,
        int $maxAge,
        int $sharedMaxAge,
        int $cacheLifetime,
    ) {
        $this->serializer = $serializer;
        $this->analyticsRepository = $analyticsRepository;
        $this->environment = $environment;
        $this->maxAge = $maxAge;
        $this->sharedMaxAge = $sharedMaxAge;
        $this->cacheLifetime = $cacheLifetime;
    }

    public function getAction(Request $request): Response
    {
        /** @var RequestAttributes $attributes */
        $attributes = $request->attributes->get('_sulu');

        /** @var Webspace $webspace */
        $webspace = $attributes->getAttribute('webspace');

        $portalUrl = $attributes->getAttribute('urlExpression');

        if (!$portalUrl) {
            return new JsonResponse([]);
        }

        /** @var Analytics[] $analyticsArray */
        $analyticsArray = $this->analyticsRepository->findByUrl(
            $portalUrl,
            $webspace->getKey(),
            $this->environment
        );

        $serializedAnalytics = $this->serializeData($analyticsArray);

        $response = new Response(
            $this->serializer->serialize($serializedAnalytics, 'json'),
            200,
            [
                'Content-Type' => 'application/json',
            ]
        );

        $response->setPublic();
        $response->setMaxAge($this->maxAge);
        $response->setSharedMaxAge($this->sharedMaxAge);
        $response->headers->set(SuluHttpCache::HEADER_REVERSE_PROXY_TTL, (string) $this->cacheLifetime);

        return $response;
    }

    /**
     * @param Analytics[] $analyticsArray
     *
     * @return mixed[]
     */
    private function serializeData(array $analyticsArray): array
    {
        $serialized = [];

        foreach ($analyticsArray as $key => $analytics) {
            $serialized[$key] = [
                'id' => $analytics->getId(),
                'title' => $analytics->getTitle(),
                'allDomains' => $analytics->isAllDomains(),
                'content' => $analytics->getContent(),
                'type' => $analytics->getType(),
                'webspace' => $analytics->getWebspaceKey(),
            ];
        }

        return $serialized;
    }
}
