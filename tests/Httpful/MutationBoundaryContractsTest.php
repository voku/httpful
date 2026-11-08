<?php

declare(strict_types=1);

namespace Httpful\tests;

use Httpful\Mime;
use Httpful\Request;
use Httpful\Response;
use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for observable boundaries found by Infection.
 *
 * These checks use only local objects: no DNS, external HTTP endpoints,
 * timers, or subprocesses. They must be stable during mutation testing.
 *
 * @internal
 */
final class MutationBoundaryContractsTest extends TestCase
{
    public function testHttp300IsRedirectRatherThanSuccess(): void
    {
        $success = (new Response())->withStatus(299);
        $redirect = (new Response())->withStatus(300);

        self::assertTrue($success->isSuccess());
        self::assertFalse($redirect->isSuccess());
        self::assertTrue($redirect->isRedirect());
    }

    public function testRequestTimeoutHasItsOwnErrorMessage(): void
    {
        $response = (new Response())->withStatus(408);

        self::assertSame(
            'Request Timeout: the server timed out waiting for the request. (408 Request Timeout)',
            $response->getErrorMessage()
        );
    }

    public function testTransferInfoPreservesExplicitFallbacks(): void
    {
        $response = new Response();

        self::assertSame('unavailable', $response->getTransferInfoValue('absent', 'unavailable'));
        self::assertSame(0.0, $response->getConnectTime());
    }

    public function testJsonContentTypeModifierDoesNotChangeOriginalRequest(): void
    {
        $original = Request::get('https://example.com/resource');
        $previousContentType = $original->getIterator()['content_type'];
        $json = $original->withContentTypeJson();

        self::assertNotSame($original, $json);
        self::assertSame($previousContentType, $original->getIterator()['content_type']);
        self::assertSame(Mime::getFullMime(Mime::JSON), $json->getIterator()['content_type']);
    }

    public function testProxyDefaultsAndCredentialsAreNotSilentlyChanged(): void
    {
        $original = Request::get('https://example.com/resource');
        $noAuth = $original->withProxy('proxy.example.com');
        $withAuth = $original->withProxy(
            'proxy.example.com',
            8080,
            \CURLAUTH_BASIC,
            'service-user',
            'service-password'
        );

        $noAuthOptions = $noAuth->getIterator()['additional_curl_opts'];
        $authOptions = $withAuth->getIterator()['additional_curl_opts'];

        self::assertNotSame($original, $noAuth);
        self::assertSame('proxy.example.com:80', $noAuthOptions[\CURLOPT_PROXY]);
        self::assertArrayNotHasKey(\CURLOPT_PROXYUSERPWD, $noAuthOptions);
        self::assertSame('proxy.example.com:8080', $authOptions[\CURLOPT_PROXY]);
        self::assertSame(\CURLAUTH_BASIC, $authOptions[\CURLOPT_PROXYAUTH]);
        self::assertSame('service-user:service-password', $authOptions[\CURLOPT_PROXYUSERPWD]);
        self::assertArrayNotHasKey(
            \CURLOPT_PROXY,
            $original->getIterator()['additional_curl_opts']
        );
    }

    public function testProxyTunnelAliasesPreserveBooleanIntent(): void
    {
        $original = Request::get('https://example.com/');
        $enabled = $original->useProxyTunnel();
        $disabled = $enabled->doNotUseProxyTunnel();

        self::assertTrue($enabled->getIterator()['additional_curl_opts'][\CURLOPT_HTTPPROXYTUNNEL]);
        self::assertFalse($disabled->getIterator()['additional_curl_opts'][\CURLOPT_HTTPPROXYTUNNEL]);
        self::assertArrayNotHasKey(
            \CURLOPT_HTTPPROXYTUNNEL,
            $original->getIterator()['additional_curl_opts']
        );
    }

    public function testConnectionRefusedRetryAliasEnablesTheFlag(): void
    {
        $original = Request::get('https://example.com/');
        $enabled = $original->retryOnConnectionRefused();
        $disabled = $enabled->doNotRetryOnConnectionRefused();

        self::assertTrue($enabled->getIterator()['retry_connection_refused']);
        self::assertFalse($disabled->getIterator()['retry_connection_refused']);
        self::assertFalse($original->getIterator()['retry_connection_refused']);
    }

    public function testInvalidRetryConfigurationExplainsTheRejectedValue(): void
    {
        $request = Request::get('https://example.com/');
        $cases = [
            [
                static fn (): Request => $request->withRetry(-1),
                'Invalid retry count provided: -1',
            ],
            [
                static fn (): Request => $request->withRetryDelay(-1),
                'Invalid retry delay provided: -1',
            ],
            [
                static fn (): Request => $request->withRetryMaxTime(-1),
                'Invalid retry max time provided: -1',
            ],
        ];

        foreach ($cases as [$attempt, $expectedMessage]) {
            try {
                $attempt();
                self::fail('Invalid retry input must throw.');
            } catch (\InvalidArgumentException $exception) {
                self::assertSame($expectedMessage, $exception->getMessage());
            }
        }
    }
}
