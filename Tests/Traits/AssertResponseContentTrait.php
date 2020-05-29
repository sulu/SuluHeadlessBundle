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

use Coduo\PHPMatcher\PHPUnit\PHPMatcherAssertions;
use Symfony\Component\HttpFoundation\Response;

trait AssertResponseContentTrait
{
    use PHPMatcherAssertions;

    /**
     * @param object $actualResponse
     */
    protected function assertResponseContent(
        string $patternFilename,
        $actualResponse,
        int $statusCode = 200,
        string $message = ''
    ): void {
        $this->assertInstanceOf(Response::class, $actualResponse);
        $responseContent = $actualResponse->getContent();
        $this->assertHttpStatusCode($statusCode, $actualResponse);
        $this->assertIsString($responseContent);

        $responsesFolder = $this->getCalledClassFolder() . \DIRECTORY_SEPARATOR . 'responses';
        $responsePattern = file_get_contents($responsesFolder . \DIRECTORY_SEPARATOR . $patternFilename);
        $this->assertIsString($responsePattern);

        $this->assertMatchesPattern(trim($responsePattern), trim($responseContent), $message);
    }

    private function getCalledClassFolder(): string
    {
        $calledClass = static::class;
        /** @var string $fileName */
        $fileName = (new \ReflectionClass($calledClass))->getFileName();

        return \dirname($fileName);
    }
}
