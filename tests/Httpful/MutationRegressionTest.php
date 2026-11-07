<?php

declare(strict_types=1);

namespace Httpful\tests;

use Httpful\Exception\ResponseException;
use Httpful\Factory;
use Httpful\Http;
use Httpful\Request;
use PHPUnit\Framework\TestCase;

/**
 * Regression assertions for escaped mutants in core HTTP request behavior.
 *
 * @internal
 */
final class MutationRegressionTest extends TestCase
{
    public function testFactoryDefaultResponseCodeIs200(): void
    {
        static::assertSame(200, (new Factory())->createResponse()->getStatusCode());
    }

    public function testFactoryIncludesMissingFilePathInError(): void
    {
        $missingPath = __DIR__ . '/__missing_stream_fixture__.txt';
        static::assertFileDoesNotExist($missingPath);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The file ' . $missingPath . ' cannot be opened.');

        (new Factory())->createStreamFromFile($missingPath, 'r');
    }

    public function testHttpReasonForRateLimitIsCorrect(): void
    {
        static::assertSame('Too Many Requests', Http::reason(429));
    }

    public function testUnknownHttpReasonIncludesTheCodeInItsError(): void
    {
        $this->expectException(ResponseException::class);
        $this->expectExceptionMessage('Unable to parse response code from HTTP response due to malformed response. Code: 999');

        Http::reason(999);
    }

    public function testConnectionTimeoutRejectsGarbagePrefix(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Request::get('http://localhost/')->withConnectionTimeoutInSeconds('invalid123');
    }

    public function testInvalidConnectionTimeoutErrorRetainsItsContext(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid connection timeout provided: '-1'");

        Request::get('http://localhost/')->withConnectionTimeoutInSeconds('-1');
    }

    public function testSettingConnectionTimeoutDoesNotMutateOriginal(): void
    {
        $original = Request::get('http://localhost/');
        $modified = $original->withConnectionTimeoutInSeconds(1);

        static::assertNotSame($original, $modified);
        static::assertFalse($original->hasConnectionTimeout());
        static::assertTrue($modified->hasConnectionTimeout());
    }

    public function testSettingNamedCurlOptionDoesNotMutateOriginal(): void
    {
        $original = Request::get('http://localhost/');
        $modified = $original->withProxyTunnel(true);

        static::assertNotSame($original, $modified);
    }
}
