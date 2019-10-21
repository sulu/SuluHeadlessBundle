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
use Sulu\Component\Content\Compat\PageInterface;
use Sulu\Component\Content\Compat\StructureInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class HeadlessWebsiteController extends AbstractController
{
    /**
     * @param PageInterface $structure
     */
    public function indexAction(
        Request $request,
        StructureInterface $structure,
        bool $preview = false,
        bool $partial = false
    ): Response {
        $requestFormat = $request->getRequestFormat();
        /** @var PageInterface $structure */
        $viewTemplate = $structure->getView() . '.' . $requestFormat . '.twig';

        if ('json' !== $request->getRequestFormat() && !$this->get('twig')->getLoader()->exists($viewTemplate)) {
            throw new HttpException(
                406,
                sprintf('Page does not exist in "%s" format.', $requestFormat)
            );
        }

        $data = $this->resolveStructure($structure);
        $json = $this->serializeData($data);

        if ('json' !== $request->getRequestFormat()) {
            return $this->render($viewTemplate, [
                'jsonData' => $json,
                'data' => $data,
            ]);
        }

        return new Response(
            $json,
            200,
            [
                'Content-Type' => 'application/json',
            ]
        );
    }

    /**
     * @return mixed[]
     */
    protected function resolveStructure(StructureInterface $structure): array
    {
        /** @var StructureResolverInterface $structureResolver */
        $structureResolver = $this->get('sulu_headless.structure_resolver');

        return $structureResolver->resolve($structure, $structure->getLanguageCode());
    }

    /**
     * @param mixed[] $data
     */
    protected function serializeData(array $data): string
    {
        return $this->get('jms_serializer')->serialize($data, 'json');
    }

    public static function getSubscribedServices()
    {
        $subscribedServices = parent::getSubscribedServices();
        $subscribedServices[] = 'sulu_headless.structure_resolver';
        $subscribedServices[] = 'jms_serializer';

        return $subscribedServices;
    }
}
