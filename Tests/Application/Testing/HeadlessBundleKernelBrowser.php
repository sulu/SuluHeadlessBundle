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

namespace Sulu\Bundle\HeadlessBundle\Tests\Application\Testing;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;

/**
 * @deprecated This class has only be introduced to keep BC between Symfony 5.2 and previous versions.
 *
 * Will be removed, as soon as the min-requirement of sulu/sulu is high enough for the `SuluKernelBrowser` to be always available.
 *
 * @internal
 */
class HeadlessBundleKernelBrowser extends KernelBrowser
{
    /**
     * @deprecated Copied from `SuluKernelBrowser` to keep BC for Symfony <5.2
     *
     * Will be removed, as soon as the min-requirement of sulu/sulu is high enough for the `SuluKernelBrowser` to be always available.
     *
     * @param mixed[] $parameters
     * @param mixed[] $server
     */
    public function jsonRequest(
        string $method,
        string $uri,
        array $parameters = [],
        array $server = [],
        bool $changeHistory = true
    ): Crawler {
        // Based on https://github.com/symfony/symfony/blob/v5.2.0/src/Symfony/Component/HttpFoundation/Request.php#L388-L404
        // the logic in symfony/http-foundation we convert parameters to json content.
        switch (strtoupper($method)) {
            case 'POST':
            case 'PUT':
            case 'DELETE':
            case 'PATCH':
                $content = json_encode($parameters, \defined('JSON_THROW_ON_ERROR') ? \JSON_THROW_ON_ERROR : 0) ?: null;
                $query = [];
                break;
            default:
                $content = null;
                $query = $parameters;
                break;
        }

        $serverContentType = $this->getServerParameter('CONTENT_TYPE', null);
        $serverHttpAccept = $this->getServerParameter('HTTP_ACCEPT', null);

        $this->setServerParameter('CONTENT_TYPE', 'application/json');
        $this->setServerParameter('HTTP_ACCEPT', 'application/json');

        try {
            return $this->request($method, $uri, $query, [], $server, $content, $changeHistory);
        } finally {
            if (null === $serverContentType) {
                unset($this->server['CONTENT_TYPE']);
            } else {
                $this->setServerParameter('CONTENT_TYPE', $serverContentType);
            }

            if (null === $serverHttpAccept) {
                unset($this->server['HTTP_ACCEPT']);
            } else {
                $this->setServerParameter('HTTP_ACCEPT', $serverHttpAccept);
            }
        }
    }
}
