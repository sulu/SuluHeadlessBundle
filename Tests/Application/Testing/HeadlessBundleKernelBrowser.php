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
        return $this->request($method, $uri, $parameters, [], $server, null, $changeHistory);
    }
}
