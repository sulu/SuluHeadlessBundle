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
use Sulu\Component\Content\Compat\StructureInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class HeadlessWebsiteController extends WebsiteController
{
    public function indexAction(
        Request $request,
        StructureInterface $structure,
        bool $preview = false,
        bool $partial = false
    ): Response {
        if ('json' !== $request->getRequestFormat()) {
            return $this->renderStructure($structure, [], $preview, $partial);
        }

        /** @var StructureResolverInterface $structureResolver */
        $structureResolver = $this->get('sulu_headless.structure_resolver');
        $data = $structureResolver->resolve($structure, $structure->getLanguageCode());

        return new Response(
            $this->get('jms_serializer')->serialize($data, 'json'),
            200,
            [
                'Content-Type' => 'application/json',
            ]
        );
    }

    public static function getSubscribedServices()
    {
        $subscribedServices = parent::getSubscribedServices();
        $subscribedServices[] = 'sulu_headless.structure_resolver';
        $subscribedServices[] = 'jms_serializer';

        return $subscribedServices;
    }
}
