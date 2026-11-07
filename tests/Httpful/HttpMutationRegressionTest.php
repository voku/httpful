<?php

declare(strict_types=1);

namespace Httpful\tests;

use Httpful\Curl\Curl;
use Httpful\Request;
use Httpful\Response;
use PHPUnit\Framework\TestCase;

/**
 * Keep externally observable HTTP behavior covered at exact boundaries.
 *
 * @internal
 */
final class HttpMutationRegressionTest extends TestCase
{
    public function testRawRequestLinePreservesQuestionMarkAndQuery(): void
    {
        $request = Request::get('http://' . \TEST_SERVER . '/foo.txt?batch=7&limit=2')
            ->withContentType('application/json')
            ->withContentCharset('UTF-8')
            ->withCacheControl('no-store')
            ->_curlPrep();

        try {
            $headers = $request->getRawHeaders();
            self::assertStringContainsString('GET /foo.txt?batch=7&limit=2 HTTP/', $headers);
            self::assertStringContainsString('Content-Type: application/json; charset=UTF-8', $headers);
            self::assertStringContainsString('Cache-Control: no-store', $headers);
        } finally {
            $request->close();
        }
    }

    public function testTlsVersionNormalizationPreservesBoundsAndWhitespace(): void
    {
        if (!\defined('CURL_SSLVERSION_TLSv1_1') || !\defined('CURL_SSLVERSION_MAX_TLSv1_2')) {
            self::markTestSkipped('Installed libcurl does not export TLS version bound constants.');
        }

        $request = Request::get('https://example.com/')
            ->withTlsVersion(' TLSv1.1 ', 'TLSv1.2');

        $options = $request->getIterator()['additional_curl_opts'];
        self::assertSame(
            \CURL_SSLVERSION_TLSv1_1 | \CURL_SSLVERSION_MAX_TLSv1_2,
            $options[\CURLOPT_SSLVERSION]
        );
    }

    public function testTlsDefaultVersionKeepsDefaultCurlConstant(): void
    {
        $request = Request::get('https://example.com/')->withTlsVersion(' default ');
        $options = $request->getIterator()['additional_curl_opts'];

        self::assertSame(\CURL_SSLVERSION_DEFAULT, $options[\CURLOPT_SSLVERSION]);
    }

    public function testInvalidTlsVersionIdentifiesUserInput(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid TLS version provided: 'bogus'");

        Request::get('https://example.com/')->withTlsVersion('bogus');
    }

    public function testRetryableGatewayTimeoutAndNonRetryable505AreDistinct(): void
    {
        foreach ([504 => true, 505 => false] as $status => $expected) {
            $request = Request::get('http://example.com/')
                ->withRetry(1)
                ->withRetryDelay(0)
                ->_curlPrep();

            $curl = new Curl();

            try {
                $decide = $request->_curl()->getRetryDecider();
                self::assertIsCallable($decide);

                $curl->error = true;
                $curl->httpError = true;
                $curl->httpStatusCode = $status;

                self::assertSame($expected, $decide($curl), "Unexpected retry for HTTP {$status}");
            } finally {
                $curl->close();
                $request->close();
            }
        }
    }

    public function testClientErrorMessageIncludesExactStatusAndPunctuation(): void
    {
        $response = (new Response())->withStatus(400);

        self::assertSame(
            'Bad Request: the server could not understand the request due to invalid syntax. (400 Bad Request)',
            $response->getErrorMessage()
        );
    }

    public function testServerErrorMessageIncludesExactStatusAndPunctuation(): void
    {
        $response = (new Response())->withStatus(500);

        self::assertSame(
            'Internal Server Error: the server encountered an unexpected error. This is a server-side problem. (500 Internal Server Error)',
            $response->getErrorMessage()
        );
    }

    public function testUnknownStatusMessageSeparatesCodeAndReason(): void
    {
        $response = (new Response())->withStatus(600, 'Unrecognized');

        self::assertSame(
            'Unknown response status (600 Unrecognized).',
            $response->getErrorMessage()
        );
    }

    public function testDebugInfoSeparatesMethodFromUrl(): void
    {
        $request = Request::get('https://example.com/articles?page=2');
        $response = new Response('ok', "HTTP/1.1 200 OK\r\n\r\n", $request);

        self::assertStringContainsString(
            "GET https://example.com/articles?page=2\n",
            $response->debugInfo()
        );
    }
}
