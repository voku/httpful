<?php

declare(strict_types=1);

namespace Httpful\tests;

use Httpful\Curl\Curl;
use Httpful\Http;
use Httpful\Request;
use Httpful\Response;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class CurlFeatureApiTest extends TestCase
{
    private function setPrivateProperty(object $object, string $property, $value): void
    {
        $reflection = new \ReflectionProperty($object, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($object, $value);
    }

    public function testWithBearerToken(): void
    {
        $req = Request::get('http://example.com/')->withBearerToken('abc123');

        static::assertSame('Bearer abc123', $req->getHeaderLine('Authorization'));
    }

    public function testWithRetryHelpersStoreConfiguration(): void
    {
        $req = Request::get('http://example.com/')
            ->withRetry(3)
            ->withRetryDelay(0.25)
            ->withRetryMaxTime(4)
            ->withRetryAllErrors()
            ->withRetryConnectionRefused();

        $iter = $req->getIterator();

        static::assertSame(3, $iter['retry']);
        static::assertSame(0.25, $iter['retry_delay']);
        static::assertSame(4, $iter['retry_max_time']);
        static::assertTrue($iter['retry_all_errors']);
        static::assertTrue($iter['retry_connection_refused']);
    }

    public function testFeatureAliasHelpersStoreConfiguration(): void
    {
        $req = Request::get('http://example.com/')
            ->authenticateWithBasicAuth('user', 'pass')
            ->authenticateWithBearerToken('abc123')
            ->withClientSideCertificateAuth('/tmp/cert.pem', '/tmp/key.pem', 'secret')
            ->alwaysSerializePayload()
            ->retry(3)
            ->retryAfter(0.25)
            ->retryForAtMost(4)
            ->retryOnAllErrors()
            ->doNotRetryOnAllErrors()
            ->retryOnConnectionRefused()
            ->doNotRetryOnConnectionRefused()
            ->useCookieFile('/tmp/cookies.in')
            ->useCookieJar('/tmp/cookies.out')
            ->useCaBundle('/tmp/ca.pem')
            ->useCaPath('/tmp/certs')
            ->pinPublicKey('sha256//abc')
            ->useProxyTunnel()
            ->doNotUseProxyTunnel()
            ->noProxy(['localhost', '127.0.0.1'])
            ->resolve('example.com:443:127.0.0.1')
            ->connectTo('example.com:443:backend.internal:8443')
            ->downloadTo('/tmp/file.zip')
            ->useHttp2PriorKnowledge()
            ->useHttp3()
            ->useHttp3Only()
            ->useTlsVersion('1.2');

        if (\defined('CURLOPT_ALTSVC') && \defined('CURLOPT_ALTSVC_CTRL')) {
            $req = $req->useAltSvcCache('/tmp/altsvc.cache', true);
        }

        if (\defined('CURLOPT_HSTS') && \defined('CURLOPT_HSTS_CTRL')) {
            $req = $req->useHstsCache('/tmp/hsts.cache', true);
        }

        $iter = $req->getIterator();
        $opts = $iter['additional_curl_opts'];

        static::assertSame('user', $iter['username']);
        static::assertSame('pass', $iter['password']);
        static::assertSame('Bearer abc123', $req->getHeaderLine('Authorization'));
        static::assertSame('/tmp/cert.pem', $iter['ssl_cert']);
        static::assertSame('/tmp/key.pem', $iter['ssl_key']);
        static::assertSame('secret', $iter['ssl_passphrase']);
        static::assertSame(Request::SERIALIZE_PAYLOAD_ALWAYS, $iter['serialize_payload_method']);
        static::assertSame(3, $iter['retry']);
        static::assertSame(0.25, $iter['retry_delay']);
        static::assertSame(4, $iter['retry_max_time']);
        static::assertFalse($iter['retry_all_errors']);
        static::assertFalse($iter['retry_connection_refused']);
        static::assertSame('/tmp/file.zip', $iter['file_path_for_download']);
        static::assertSame(Http::HTTP_3, $iter['protocol_version']);
        static::assertSame('CURL_HTTP_VERSION_3ONLY', $iter['curl_http_version']);
        static::assertSame('/tmp/cookies.in', $opts[\CURLOPT_COOKIEFILE]);
        static::assertSame('/tmp/cookies.out', $opts[\CURLOPT_COOKIEJAR]);
        static::assertSame('/tmp/ca.pem', $opts[\CURLOPT_CAINFO]);
        static::assertSame('/tmp/certs', $opts[\CURLOPT_CAPATH]);
        static::assertSame('sha256//abc', $opts[\CURLOPT_PINNEDPUBLICKEY]);
        static::assertFalse($opts[\CURLOPT_HTTPPROXYTUNNEL]);
        static::assertSame('localhost,127.0.0.1', $opts[\CURLOPT_NOPROXY]);
        static::assertSame(['example.com:443:127.0.0.1'], $opts[\CURLOPT_RESOLVE]);
        static::assertSame(['example.com:443:backend.internal:8443'], $opts[\CURLOPT_CONNECT_TO]);
        static::assertArrayHasKey(\CURLOPT_SSLVERSION, $opts);

        if (\defined('CURLOPT_ALTSVC') && \defined('CURLOPT_ALTSVC_CTRL')) {
            static::assertSame('/tmp/altsvc.cache', $opts[\CURLOPT_ALTSVC]);
            static::assertArrayHasKey(\CURLOPT_ALTSVC_CTRL, $opts);
        }

        if (\defined('CURLOPT_HSTS') && \defined('CURLOPT_HSTS_CTRL')) {
            static::assertSame('/tmp/hsts.cache', $opts[\CURLOPT_HSTS]);
            static::assertArrayHasKey(\CURLOPT_HSTS_CTRL, $opts);
        }
    }

    public function testWithRetryRejectsInvalidCount(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Request::get('http://example.com/')->withRetry('abc');
    }

    public function testWithRetryDelayRejectsInvalidValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Request::get('http://example.com/')->withRetryDelay('abc');
    }

    public function testWithRetryMaxTimeRejectsInvalidValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Request::get('http://example.com/')->withRetryMaxTime('abc');
    }

    public function testRetryDeciderRetriesTimeoutsUntilLimit(): void
    {
        $req = Request::get('http://example.com/')
            ->withRetry(2)
            ->withRetryDelay(0)
            ->_curlPrep();

        $decider = $req->_curl()->getRetryDecider();
        static::assertIsCallable($decider);

        $curl = new Curl();
        $curl->error = true;
        $curl->curlError = true;
        $curl->curlErrorCode = \CURLE_OPERATION_TIMEOUTED;
        $curl->curlErrorMessage = 'timeout';

        static::assertTrue($decider($curl));
        static::assertTrue($decider($curl));
        static::assertFalse($decider($curl));

        $curl->close();
        $req->close();
    }

    public function testRetryDeciderUsesExponentialBackoffByDefault(): void
    {
        $req = Request::get('http://example.com/')
            ->withRetry(1)
            ->_curlPrep();

        $decider = $req->_curl()->getRetryDecider();
        $curl = new Curl();
        $curl->error = true;
        $curl->httpError = true;
        $curl->httpStatusCode = 503;

        static::assertTrue($decider($curl));

        $curl->close();
        $req->close();
    }

    public function testRetryDeciderSupportsConnectionRefusedAndRetryAllErrors(): void
    {
        $connectionRefusedRequest = Request::get('http://example.com/')
            ->withRetry(1)
            ->withRetryDelay(0)
            ->withRetryConnectionRefused()
            ->_curlPrep();
        $connectionRefusedDecider = $connectionRefusedRequest->_curl()->getRetryDecider();

        $connectionRefusedCurl = new Curl();
        $connectionRefusedCurl->error = true;
        $connectionRefusedCurl->curlError = true;
        $connectionRefusedCurl->curlErrorCode = \CURLE_COULDNT_CONNECT;
        $connectionRefusedCurl->curlErrorMessage = 'Connection refused';

        static::assertTrue($connectionRefusedDecider($connectionRefusedCurl));

        $retryAllErrorsRequest = Request::get('http://example.com/')
            ->withRetry(1)
            ->withRetryDelay(0)
            ->withRetryAllErrors()
            ->_curlPrep();
        $retryAllErrorsDecider = $retryAllErrorsRequest->_curl()->getRetryDecider();

        $retryAllErrorsCurl = new Curl();
        $retryAllErrorsCurl->error = true;
        $retryAllErrorsCurl->httpError = true;
        $retryAllErrorsCurl->httpStatusCode = 404;

        static::assertTrue($retryAllErrorsDecider($retryAllErrorsCurl));

        $connectionRefusedCurl->close();
        $retryAllErrorsCurl->close();
        $connectionRefusedRequest->close();
        $retryAllErrorsRequest->close();
    }

    public function testRetryDeciderStopsForNonRetryableCurlErrors(): void
    {
        $req = Request::get('http://example.com/')
            ->withRetry(1)
            ->withRetryDelay(0)
            ->_curlPrep();

        $decider = $req->_curl()->getRetryDecider();
        $curl = new Curl();
        $curl->error = true;
        $curl->curlError = true;
        $curl->curlErrorCode = \CURLE_COULDNT_CONNECT;
        $curl->curlErrorMessage = 'No route to host';

        static::assertFalse($decider($curl));

        $curl->close();
        $req->close();
    }

    public function testRetryDeciderRetriesRetryableHttpStatusCodes(): void
    {
        $req = Request::get('http://example.com/')
            ->withRetry(1)
            ->withRetryDelay(0)
            ->_curlPrep();

        $decider = $req->_curl()->getRetryDecider();
        $curl = new Curl();
        $curl->error = true;
        $curl->httpError = true;
        $curl->httpStatusCode = 503;

        static::assertTrue($decider($curl));

        $curl->close();
        $req->close();
    }

    public function testRetryDeciderRespectsRetryMaxTime(): void
    {
        $req = Request::get('http://example.com/')
            ->withRetry(1)
            ->withRetryDelay(1)
            ->withRetryMaxTime(0)
            ->_curlPrep();

        $decider = $req->_curl()->getRetryDecider();
        $curl = new Curl();
        $curl->error = true;
        $curl->httpError = true;
        $curl->httpStatusCode = 503;

        static::assertFalse($decider($curl));

        $curl->close();
        $req->close();
    }

    public function testRetryDeciderSleepsWhenDelayIsConfigured(): void
    {
        $req = Request::get('http://example.com/')
            ->withRetry(1)
            ->withRetryDelay(0.001)
            ->_curlPrep();

        $decider = $req->_curl()->getRetryDecider();
        $curl = new Curl();
        $curl->error = true;
        $curl->httpError = true;
        $curl->httpStatusCode = 503;

        static::assertTrue($decider($curl));

        $curl->close();
        $req->close();
    }

    public function testRetryMaxTimeStartsAtFirstRetryDecision(): void
    {
        $req = Request::get('http://example.com/')
            ->withRetry(1)
            ->withRetryDelay(0)
            ->withRetryMaxTime(0.05)
            ->_curlPrep();

        $decider = $req->_curl()->getRetryDecider();

        \usleep(100000);

        $curl = new Curl();
        $curl->error = true;
        $curl->httpError = true;
        $curl->httpStatusCode = 503;

        static::assertTrue($decider($curl));

        $curl->close();
        $req->close();
    }

    public function testAdvancedCurlOptionHelpers(): void
    {
        $req = Request::get('http://example.com/')
            ->withCookieFile('/tmp/cookies.in')
            ->withCookieJar('/tmp/cookies.out')
            ->withCaBundle('/tmp/ca.pem')
            ->withCaPath('/tmp/certs')
            ->withPinnedPublicKey('sha256//abc')
            ->withProxyTunnel()
            ->withNoProxy(['localhost', '127.0.0.1'])
            ->withResolve(['example.com:443:127.0.0.1'])
            ->withConnectTo(['example.com:443:backend.internal:8443']);

        if (\defined('CURL_SSLVERSION_MAX_TLSv1_3')) {
            $req = $req->withTlsVersion('1.2', '1.3');
        } else {
            $req = $req->withTlsVersion('1.2');
        }

        $opts = $req->getIterator()['additional_curl_opts'];

        static::assertSame('/tmp/cookies.in', $opts[\CURLOPT_COOKIEFILE]);
        static::assertSame('/tmp/cookies.out', $opts[\CURLOPT_COOKIEJAR]);
        static::assertSame('/tmp/ca.pem', $opts[\CURLOPT_CAINFO]);
        static::assertSame('/tmp/certs', $opts[\CURLOPT_CAPATH]);
        static::assertSame('sha256//abc', $opts[\CURLOPT_PINNEDPUBLICKEY]);
        static::assertTrue($opts[\CURLOPT_HTTPPROXYTUNNEL]);
        static::assertSame('localhost,127.0.0.1', $opts[\CURLOPT_NOPROXY]);
        static::assertSame(['example.com:443:127.0.0.1'], $opts[\CURLOPT_RESOLVE]);
        static::assertSame(['example.com:443:backend.internal:8443'], $opts[\CURLOPT_CONNECT_TO]);
        static::assertArrayHasKey(\CURLOPT_SSLVERSION, $opts);
    }

    public function testWithTlsVersionValidatesBounds(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Request::get('http://example.com/')->withTlsVersion('1.3', '1.2');
    }

    public function testWithTlsVersionAcceptsDefaultAndIntegerOptions(): void
    {
        $reqDefault = Request::get('http://example.com/')->withTlsVersion('default');
        static::assertSame(\CURL_SSLVERSION_DEFAULT, $reqDefault->getIterator()['additional_curl_opts'][\CURLOPT_SSLVERSION]);

        $reqInteger = Request::get('http://example.com/')->withTlsVersion(\CURL_SSLVERSION_TLSv1_2);
        static::assertSame(\CURL_SSLVERSION_TLSv1_2, $reqInteger->getIterator()['additional_curl_opts'][\CURLOPT_SSLVERSION]);
    }

    public function testWithTlsVersionRejectsInvalidVersion(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Request::get('http://example.com/')->withTlsVersion('bogus');
    }

    public function testResolveAndConnectToAcceptScalarStrings(): void
    {
        $req = Request::get('http://example.com/')
            ->withResolve('example.com:443:127.0.0.1')
            ->withConnectTo('example.com:443:backend.internal:8443');

        $opts = $req->getIterator()['additional_curl_opts'];

        static::assertSame(['example.com:443:127.0.0.1'], $opts[\CURLOPT_RESOLVE]);
        static::assertSame(['example.com:443:backend.internal:8443'], $opts[\CURLOPT_CONNECT_TO]);
    }

    public function testResolveRejectsInvalidEntries(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Request::get('http://example.com/')->withResolve([123]);
    }

    public function testAltSvcAndHstsHelpers(): void
    {
        if (
            !\defined('CURLOPT_ALTSVC')
            || !\defined('CURLOPT_ALTSVC_CTRL')
            || !\defined('CURLOPT_HSTS')
            || !\defined('CURLOPT_HSTS_CTRL')
        ) {
            static::markTestSkipped('Alt-Svc and HSTS are not supported by this cURL build.');
        }

        $req = Request::get('http://example.com/')
            ->withAltSvcCache('/tmp/altsvc.cache', true)
            ->withHstsCache('/tmp/hsts.cache', true);

        $opts = $req->getIterator()['additional_curl_opts'];

        $altSvcControl = 0;
        foreach (['CURLALTSVC_H1', 'CURLALTSVC_H2', 'CURLALTSVC_H3'] as $constantName) {
            if (\defined($constantName)) {
                $altSvcControl |= (int) \constant($constantName);
            }
        }
        if (\defined('CURLALTSVC_READONLYFILE')) {
            $altSvcControl |= (int) \constant('CURLALTSVC_READONLYFILE');
        }

        $hstsControl = (int) \constant('CURLHSTS_ENABLE');
        if (\defined('CURLHSTS_READONLYFILE')) {
            $hstsControl |= (int) \constant('CURLHSTS_READONLYFILE');
        }

        static::assertSame('/tmp/altsvc.cache', $opts[\CURLOPT_ALTSVC]);
        static::assertSame($altSvcControl, $opts[\CURLOPT_ALTSVC_CTRL]);
        static::assertSame('/tmp/hsts.cache', $opts[\CURLOPT_HSTS]);
        static::assertSame($hstsControl, $opts[\CURLOPT_HSTS_CTRL]);
    }

    public function testHttpVersionHelpersUpdateRequestState(): void
    {
        $http11 = Request::get('http://example.com/')->withProtocolVersion(Http::HTTP_1_1);
        static::assertSame(\CURL_HTTP_VERSION_1_1, $http11->getIterator()['curl_http_version']);

        $req = Request::get('http://example.com/')->withHttp2PriorKnowledge();
        $iter = $req->getIterator();
        static::assertSame(Http::HTTP_2_0, $iter['protocol_version']);
        static::assertSame('CURL_HTTP_VERSION_2_PRIOR_KNOWLEDGE', $iter['curl_http_version']);

        $http2Tls = Request::get('http://example.com/')->withHttp2Tls();
        static::assertSame('CURL_HTTP_VERSION_2TLS', $http2Tls->getIterator()['curl_http_version']);

        $http3Version = Request::get('http://example.com/')->withProtocolVersion(Http::HTTP_3);
        static::assertSame('CURL_HTTP_VERSION_3', $http3Version->getIterator()['curl_http_version']);

        $http3 = Request::get('http://example.com/')->withHttp3Only();
        $http3Iter = $http3->getIterator();
        static::assertSame(Http::HTTP_3, $http3Iter['protocol_version']);
        static::assertSame('CURL_HTTP_VERSION_3ONLY', $http3Iter['curl_http_version']);
    }

    public function testProtocolVersionPreparationHandlesBuiltinVersionsAndFallback(): void
    {
        $request10 = Request::get('http://example.com/');
        $this->setPrivateProperty($request10, 'protocol_version', Http::HTTP_1_0);
        $request10->_curlPrep();
        $request10->close();

        $request20 = Request::get('http://example.com/');
        $this->setPrivateProperty($request20, 'protocol_version', Http::HTTP_2_0);
        $request20->_curlPrep();
        $request20->close();

        $requestFallback = Request::get('http://example.com/');
        $this->setPrivateProperty($requestFallback, 'protocol_version', '9.9');
        $requestFallback->_curlPrep();
        $requestFallback->close();

        static::assertTrue(true);
    }

    public function testHttp3PreparationFailsCleanlyWhenUnsupported(): void
    {
        if (\defined('CURL_HTTP_VERSION_3')) {
            static::markTestSkipped('HTTP/3 is supported by this cURL build.');
        }

        $this->expectException(\RuntimeException::class);

        Request::get('http://example.com/')
            ->withHttp3()
            ->_curlPrep();
    }

    public function testHttp3ProtocolFallbackPathFailsCleanlyWhenUnsupported(): void
    {
        if (\defined('CURL_HTTP_VERSION_3')) {
            static::markTestSkipped('HTTP/3 is supported by this cURL build.');
        }

        $request = Request::get('http://example.com/');
        $this->setPrivateProperty($request, 'protocol_version', Http::HTTP_3);
        $this->setPrivateProperty($request, 'curl_http_version', null);

        $this->expectException(\RuntimeException::class);

        $request->_curlPrep();
    }

    public function testCurlAttemptRetryWithIntegerRetries(): void
    {
        $curl = new Curl();
        $curl->error = true;
        $curl->setRetry(2);

        static::assertTrue($curl->attemptRetry());
        static::assertSame(1, $curl->getRetries());
        static::assertSame(1, $curl->getRemainingRetries());
        static::assertTrue($curl->attemptRetry());
        static::assertFalse($curl->attemptRetry());

        $curl->close();
    }

    public function testCurlAttemptRetryWithCallable(): void
    {
        $curl = new Curl();
        $curl->error = true;
        $curl->setRetry(static function (): bool {
            return true;
        });

        static::assertTrue($curl->attemptRetry());
        static::assertSame(1, $curl->getRetries());
        static::assertSame(0, $curl->getRemainingRetries());

        $curl->close();
    }

    public function testCurlResetReinitializesWhenHandleIsMissing(): void
    {
        $curl = new Curl();
        $this->setPrivateProperty($curl, 'curl', false);

        $curl->reset();

        static::assertNotFalse($curl->getCurl());
        $curl->close();
    }

    public function testResponseTransferInfoHelpers(): void
    {
        $response = new Response(
            '',
            "HTTP/2 200 OK\r\n\r\n",
            Request::get('http://example.com/'),
            [
                'url'             => 'https://example.com/final',
                'primary_ip'      => '127.0.0.1',
                'local_ip'        => '127.0.0.2',
                'redirect_count'  => 2,
                'total_time'      => 0.25,
                'connect_time'    => 0.05,
                'appconnect_time' => 0.10,
                'http_version'    => \defined('CURL_HTTP_VERSION_2_PRIOR_KNOWLEDGE')
                    ? (int) \constant('CURL_HTTP_VERSION_2_PRIOR_KNOWLEDGE')
                    : \CURL_HTTP_VERSION_2_0,
            ]
        );

        static::assertSame('https://example.com/final', $response->getEffectiveUrl());
        static::assertSame('127.0.0.1', $response->getPrimaryIp());
        static::assertSame('127.0.0.2', $response->getLocalIp());
        static::assertSame(2, $response->getRedirectCount());
        static::assertSame(0.25, $response->getTotalTime());
        static::assertSame(0.05, $response->getConnectTime());
        static::assertSame(0.10, $response->getTlsHandshakeTime());
        static::assertSame(Http::HTTP_2_0, $response->getTransferHttpVersion());
        static::assertSame($response->getMetaData(), $response->getTransferInfo());
    }

    public function testResponseTransferInfoFallbacksAndStringVersions(): void
    {
        $response = new Response(
            '',
            "HTTP/1.1 200 OK\r\n\r\n",
            Request::get('http://example.com/'),
            [
                'primary_ip'   => '',
                'http_version' => 'custom-version',
            ]
        );

        static::assertNull($response->getPrimaryIp());
        static::assertSame('custom-version', $response->getTransferHttpVersion());

        $fallbackResponse = new Response(
            '',
            "HTTP/1.1 200 OK\r\n\r\n",
            Request::get('http://example.com/'),
            []
        );

        static::assertSame(Http::HTTP_1_1, $fallbackResponse->getTransferHttpVersion());
    }
}
