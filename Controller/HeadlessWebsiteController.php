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

use Sulu\Bundle\HeadlessBundle\Content\StructureResolverInterface;
use Sulu\Bundle\WebsiteBundle\Controller\WebsiteController;
use Sulu\Component\Content\Compat\PageInterface;
use Sulu\Component\Content\Compat\StructureInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class HeadlessWebsiteController extends WebsiteController
{
    /**
     * We cannot set the typehint of the $structure parameter to PageInterface because the ArticleBundle does not
     * implement that interface. Therefore we need to define the type via phpdoc to satisfy phpstan.
     *
     * @param PageInterface $structure
     */
    public function indexAction(
        Request $request,
        StructureInterface $structure,
        bool $preview = false,
        bool $partial = false
    ): Response {
        $headlessData = $this->resolveStructure($structure);

        if ('json' !== $request->getRequestFormat()) {
            return $this->renderStructure($structure, ['headless' => $headlessData], $preview, $partial);
        }

        $response = new Response($this->serializeData($headlessData));
        $response->headers->set('Content-Type', 'application/json');

        $cacheLifetimeEnhancer = $this->getCacheTimeLifeEnhancer();
        if (!$preview && $cacheLifetimeEnhancer) {
            $cacheLifetimeEnhancer->enhance($response, $structure);
        }

        return $response;
    }

    /**
     * @return mixed[]
     */
    protected function resolveStructure(StructureInterface $structure): array
    {
        /** @var StructureResolverInterface $structureResolver */
        $structureResolver = $this->container->get('sulu_headless.structure_resolver');

        return $structureResolver->resolve($structure, $structure->getLanguageCode());
    }

    /**
     * @param mixed[] $data
     */
    protected function serializeData(array $data): string
    {
        return \json_encode($data, \JSON_THROW_ON_ERROR);
    }

    /**
     * @return mixed[]
     */
    public static function getSubscribedServices(): array
    {
        $subscribedServices = parent::getSubscribedServices();
        $subscribedServices['sulu_headless.structure_resolver'] = StructureResolverInterface::class;

        return $subscribedServices;
    }
}
