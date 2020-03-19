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

namespace Sulu\Bundle\HeadlessBundle\Tests\Traits;

use Symfony\Component\HttpFoundation\Response;

trait AssertResponseContentTypeTrait
{
    /**
     * @param object $response
     */
    protected static function assertResponseContentType(
        string $expectContentType,
        $response
    ): void {
        self::assertInstanceOf(Response::class, $response);
        $contentType = $response->headers->get('Content-Type');
        self::assertNotNull($contentType);
        self::assertStringStartsWith($expectContentType, $contentType);
    }
}
