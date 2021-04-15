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
use Sulu\Bundle\HeadlessBundle\Content\SnippetResolverInterface;
use Sulu\Bundle\SnippetBundle\Snippet\DefaultSnippetManagerInterface;
use Sulu\Component\Rest\RequestParametersTrait;
use Sulu\Component\Webspace\Analyzer\Attributes\RequestAttributes;
use Sulu\Component\Webspace\Webspace;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SnippetAreaController
{
    use RequestParametersTrait;

    /**
     * @var DefaultSnippetManagerInterface
     */
    private $defaultSnippetManager;

    /**
     * @var SnippetResolverInterface
     */
    private $snippetResolver;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    public function __construct(
        DefaultSnippetManagerInterface $defaultSnippetManager,
        SnippetResolverInterface $snippetResolver,
        SerializerInterface $serializer
    ) {
        $this->defaultSnippetManager = $defaultSnippetManager;
        $this->snippetResolver = $snippetResolver;
        $this->serializer = $serializer;
    }

    public function getAction(Request $request, string $area): Response
    {
        /** @var RequestAttributes $attributes */
        $attributes = $request->attributes->get('_sulu');

        /** @var Webspace $webspace */
        $webspace = $attributes->getAttribute('webspace');
        $webspaceKey = $webspace->getKey();
        $locale = $request->getLocale();

        $loadExcerpt = (bool) $this->getRequestParameter($request, 'loadExcerpt', true, false);

        $snippet = $this->defaultSnippetManager->load($webspaceKey, $area, $locale);

        if (null === $snippet) {
            throw new NotFoundHttpException(sprintf('No snippet found for area "%s"', $area));
        }

        $resolvedSnippet = $this->snippetResolver->resolve(
            $snippet->getUuid(),
            $webspaceKey,
            $locale,
            null,
            $loadExcerpt
        );

        return new Response(
            $this->serializer->serialize(
                $resolvedSnippet,
                'json',
                (new SerializationContext())->setSerializeNull(true)
            ),
            200,
            [
                'Content-Type' => 'application/json',
            ]
        );
    }
}
