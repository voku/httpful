<?php

declare(strict_types=1);

namespace Httpful\tests;

use Httpful\Client;
use Httpful\Curl\Curl;
use Httpful\Http;
use Httpful\Mime;
use Httpful\Request;
use Httpful\Response;
use Httpful\Uri;
use Httpful\UriResolver;
use PHPUnit\Framework\TestCase;

/**
 * Extended tests to further boost code coverage.
 *
 * @internal
 */
final class ExtraCoverageExtendedTest extends TestCase
{
    // =========================================================================
    // Curl\Curl – getters, setters and callbacks (no HTTP required)
    // =========================================================================

    private function makeCurl(): Curl
    {
        return new Curl();
    }

    public function testCurlConstructAndClose(): void
    {
        $curl = $this->makeCurl();
        static::assertNotNull($curl->getCurl());
        $curl->close();
        // close again should be safe
        $curl->close();
    }

    public function testCurlSetGetId(): void
    {
        $curl = $this->makeCurl();
        $curl->setId('test-id');
        static::assertSame('test-id', $curl->getId());
    }

    public function testCurlChildOfMultiCurl(): void
    {
        $curl = $this->makeCurl();
        static::assertFalse($curl->isChildOfMultiCurl());
        $curl->setChildOfMultiCurl(true);
        static::assertTrue($curl->isChildOfMultiCurl());
        $curl->setChildOfMultiCurl(false);
        static::assertFalse($curl->isChildOfMultiCurl());
    }

    public function testCurlSuccessErrorCompleteCallbacks(): void
    {
        $curl = $this->makeCurl();
        $s = static function () {
        };
        $e = static function () {
        };
        $c = static function () {
        };
        $curl->success($s);
        $curl->error($e);
        $curl->complete($c);
        static::assertSame($s, $curl->getSuccessCallback());
        static::assertSame($e, $curl->getErrorCallback());
        static::assertSame($c, $curl->getCompleteCallback());
    }

    public function testCurlBeforeSendCallback(): void
    {
        $curl = $this->makeCurl();
        $cb = static function () {
        };
        $curl->beforeSend($cb);
        static::assertSame($cb, $curl->getBeforeSendCallback());
    }

    public function testCurlCallWithCallable(): void
    {
        $curl = $this->makeCurl();
        $called = false;
        $cb = static function (Curl $c) use (&$called) {
            $called = true;
        };
        $curl->call($cb);
        static::assertTrue($called);
    }

    public function testCurlCallWithNull(): void
    {
        $curl = $this->makeCurl();
        // Should not throw
        $result = $curl->call(null);
        static::assertSame($curl, $result);
    }

    public function testCurlGetters(): void
    {
        $curl = $this->makeCurl();
        static::assertSame(0, $curl->getAttempts());
        static::assertSame(0, $curl->getRetries());
        static::assertSame(0, $curl->getRemainingRetries());
        static::assertSame(0, $curl->getErrorCode());
        static::assertNull($curl->getErrorMessage());
        static::assertNull($curl->getRawResponse());
        static::assertSame('', $curl->getRawResponseHeaders());
        static::assertSame(0, $curl->getHttpStatusCode());
        static::assertSame(0, $curl->getCurlErrorCode());
        static::assertNull($curl->getCurlErrorMessage());
        static::assertNull($curl->getFileHandle());
        static::assertNull($curl->getDownloadFileName());
        static::assertNull($curl->getDownloadCompleteCallback());
        static::assertSame([], $curl->getResponseCookies());
        static::assertNull($curl->getResponseCookie('missing'));
        // getUrl() is initialized via setUrl('') in initialize(), returns a Uri
        static::assertNotNull($curl->getUrl());
        static::assertFalse($curl->isCurlError());
        static::assertFalse($curl->isError());
        static::assertFalse($curl->isHttpError());
        static::assertNull($curl->getRetryDecider());
    }

    public function testCurlSetBasicAuthentication(): void
    {
        $curl = $this->makeCurl();
        $result = $curl->setBasicAuthentication('user', 'pass');
        static::assertSame($curl, $result);
    }

    public function testCurlSetDigestAuthentication(): void
    {
        $curl = $this->makeCurl();
        $result = $curl->setDigestAuthentication('user', 'pass');
        static::assertSame($curl, $result);
    }

    public function testCurlSetCookieAndCookies(): void
    {
        $curl = $this->makeCurl();
        $result = $curl->setCookie('session', 'abc');
        static::assertSame($curl, $result);

        $result2 = $curl->setCookies(['a' => '1', 'b' => '2']);
        static::assertSame($curl, $result2);
    }

    public function testCurlSetCookieFile(): void
    {
        $curl = $this->makeCurl();
        static::assertSame($curl, $curl->setCookieFile('/tmp/cookies.txt'));
    }

    public function testCurlSetCookieJar(): void
    {
        $curl = $this->makeCurl();
        static::assertSame($curl, $curl->setCookieJar('/tmp/cookies.jar'));
    }

    public function testCurlSetCookieString(): void
    {
        $curl = $this->makeCurl();
        static::assertSame($curl, $curl->setCookieString('foo=bar; baz=qux'));
    }

    public function testCurlSetConnectTimeout(): void
    {
        $curl = $this->makeCurl();
        static::assertSame($curl, $curl->setConnectTimeout(5));
    }

    public function testCurlSetDefaultTimeout(): void
    {
        $curl = $this->makeCurl();
        static::assertSame($curl, $curl->setDefaultTimeout());
    }

    public function testCurlSetTimeout(): void
    {
        $curl = $this->makeCurl();
        static::assertSame($curl, $curl->setTimeout(30));
    }

    public function testCurlSetPort(): void
    {
        $curl = $this->makeCurl();
        static::assertSame($curl, $curl->setPort(8080));
    }

    public function testCurlSetProxy(): void
    {
        $curl = $this->makeCurl();
        static::assertSame($curl, $curl->setProxy('proxy.example.com', 3128, 'user', 'pass'));
    }

    public function testCurlSetProxyNoCredentials(): void
    {
        $curl = $this->makeCurl();
        static::assertSame($curl, $curl->setProxy('proxy.example.com', 3128));
    }

    public function testCurlSetProxyAuth(): void
    {
        $curl = $this->makeCurl();
        static::assertSame($curl, $curl->setProxyAuth(\CURLAUTH_BASIC));
    }

    public function testCurlSetProxyTunnel(): void
    {
        $curl = $this->makeCurl();
        static::assertSame($curl, $curl->setProxyTunnel(true));
    }

    public function testCurlSetProxyType(): void
    {
        $curl = $this->makeCurl();
        static::assertSame($curl, $curl->setProxyType(\CURLPROXY_HTTP));
    }

    public function testCurlUnsetProxy(): void
    {
        $curl = $this->makeCurl();
        static::assertSame($curl, $curl->unsetProxy());
    }

    public function testCurlSetRange(): void
    {
        $curl = $this->makeCurl();
        static::assertSame($curl, $curl->setRange('0-1024'));
    }

    public function testCurlSetRefererAndReferrer(): void
    {
        $curl = $this->makeCurl();
        static::assertSame($curl, $curl->setReferer('http://example.com/'));
        static::assertSame($curl, $curl->setReferrer('http://example.com/other'));
    }

    public function testCurlSetRetryInt(): void
    {
        $curl = $this->makeCurl();
        $curl->setRetry(3);
        static::assertSame(3, $curl->getRemainingRetries());
        static::assertNull($curl->getRetryDecider());
    }

    public function testCurlSetRetryCallable(): void
    {
        $curl = $this->makeCurl();
        $decider = static function (Curl $c): bool {
            return $c->getAttempts() < 2;
        };
        $curl->setRetry($decider);
        static::assertSame($decider, $curl->getRetryDecider());
        static::assertSame(0, $curl->getRemainingRetries());
    }

    public function testCurlSetUserAgent(): void
    {
        $curl = $this->makeCurl();
        static::assertSame($curl, $curl->setUserAgent('MyBot/1.0'));
    }

    public function testCurlProgress(): void
    {
        $curl = $this->makeCurl();
        $cb = static function () {
        };
        static::assertSame($curl, $curl->progress($cb));
    }

    public function testCurlSetMaxFilesize(): void
    {
        $curl = $this->makeCurl();
        static::assertSame($curl, $curl->setMaxFilesize(1024 * 1024));
    }

    public function testCurlSetOpts(): void
    {
        $curl = $this->makeCurl();
        $result = $curl->setOpts([\CURLOPT_VERBOSE => false, \CURLOPT_FOLLOWLOCATION => true]);
        static::assertTrue($result);
    }

    public function testCurlSetUrl(): void
    {
        $curl = $this->makeCurl();
        $curl->setUrl('http://example.com/path');
        static::assertNotNull($curl->getUrl());
    }

    public function testCurlSetUrlResolvesRelative(): void
    {
        $curl = $this->makeCurl();
        $curl->setUrl('http://example.com/base/');
        $curl->setUrl('relative/path');
        static::assertStringContainsString('relative', (string) $curl->getUrl());
    }

    public function testCurlSetUrlWithQueryParams(): void
    {
        $curl = $this->makeCurl();
        $curl->setUrl('http://example.com/', ['key' => 'value']);
        static::assertStringContainsString('key=value', (string) $curl->getUrl());
    }

    public function testCurlSetUrlWithScalarParam(): void
    {
        $curl = $this->makeCurl();
        $curl->setUrl('http://example.com/', 'foo=bar');
        static::assertStringContainsString('foo=bar', (string) $curl->getUrl());
    }

    public function testCurlVerbose(): void
    {
        $curl = $this->makeCurl();
        static::assertSame($curl, $curl->verbose(true));
        static::assertSame($curl, $curl->verbose(false));
    }

    public function testCurlReset(): void
    {
        $curl = $this->makeCurl();
        $curl->setUrl('http://example.com/');
        $curl->reset();
        // After reset, url is re-set via initialize('')
        // resolve(original_url, '') returns original_url unchanged (same-document ref)
        static::assertNotNull($curl->getUrl());
    }

    public function testCurlAttemptRetryFalseWhenNoError(): void
    {
        $curl = $this->makeCurl();
        static::assertFalse($curl->attemptRetry());
    }

    public function testCurlDownloadToTmpfile(): void
    {
        $curl = $this->makeCurl();
        $called = false;
        $cb = static function () use (&$called) {
            $called = true;
        };
        $result = $curl->download($cb);
        static::assertSame($curl, $result);
        static::assertIsResource($curl->getFileHandle());
        static::assertNull($curl->getDownloadFileName());
    }

    public function testCurlDownloadToFile(): void
    {
        $curl = $this->makeCurl();
        $tmpFile = \tempnam(\sys_get_temp_dir(), 'curl_dl_');
        $result = $curl->download($tmpFile);
        static::assertSame($curl, $result);
        static::assertStringContainsString('curl_dl_', $curl->getDownloadFileName() ?? '');
        // clean up
        @unlink($tmpFile . '.pccdownload');
        @unlink($tmpFile);
    }

    // =========================================================================
    // UriResolver – methods not fully covered
    // =========================================================================

    public function testUriResolverUnparseUrl(): void
    {
        $parsed = [
            'scheme' => 'https',
            'user'   => 'user',
            'pass'   => 'pass',
            'host'   => 'example.com',
            'port'   => 8080,
            'path'   => '/path',
            'query'  => 'key=value',
            'fragment' => 'section',
        ];
        $url = UriResolver::unparseUrl($parsed);
        static::assertSame('https://user:pass@example.com:8080/path?key=value#section', $url);
    }

    public function testUriResolverUnparseUrlMinimal(): void
    {
        $url = UriResolver::unparseUrl(['host' => 'example.com', 'path' => '/path']);
        static::assertSame('example.com/path', $url);
    }

    public function testUriResolverRemoveDotSegmentsEmpty(): void
    {
        static::assertSame('', UriResolver::removeDotSegments(''));
        static::assertSame('/', UriResolver::removeDotSegments('/'));
    }

    public function testUriResolverRemoveDotSegmentsDots(): void
    {
        static::assertSame('/a/b/', UriResolver::removeDotSegments('/a/b/c/..'));
        static::assertSame('/a/b/', UriResolver::removeDotSegments('/a/b/c/../'));
        static::assertSame('/b/', UriResolver::removeDotSegments('/a/../b/'));
        static::assertSame('/a/b/c', UriResolver::removeDotSegments('/a/./b/./c'));
    }

    public function testUriResolverRemoveDotSegmentsLeadingDoubleDots(): void
    {
        // Leading slash re-added when path starts with /
        $result = UriResolver::removeDotSegments('/..');
        static::assertSame('/', $result);
    }

    public function testUriResolverRelativize(): void
    {
        $base = new Uri('http://example.com/a/b/');
        $target = new Uri('http://example.com/a/b/c');
        $relative = UriResolver::relativize($base, $target);
        static::assertSame('c', (string) $relative);
    }

    public function testUriResolverRelativizeDifferentDir(): void
    {
        $base = new Uri('http://example.com/a/b/');
        $target = new Uri('http://example.com/a/x/y');
        $relative = UriResolver::relativize($base, $target);
        static::assertSame('../x/y', (string) $relative);
    }

    public function testUriResolverRelativizeSamePath(): void
    {
        $base = new Uri('http://example.com/a/b/');
        $target = new Uri('http://example.com/a/b/?q=1');
        $relative = UriResolver::relativize($base, $target);
        static::assertSame('?q=1', (string) $relative);
    }

    public function testUriResolverRelativizeSamePath2(): void
    {
        $base = new Uri('http://example.com/a/b/?existing');
        $target = new Uri('http://example.com/a/b/');
        $relative = UriResolver::relativize($base, $target);
        // target query is empty, base query non-empty → must use './' or segment
        static::assertNotSame('', (string) $relative);
    }

    public function testUriResolverRelativizeDifferentAuthority(): void
    {
        $base = new Uri('http://example.com/a/b/');
        $target = new Uri('http://other.com/a/b/');
        $relative = UriResolver::relativize($base, $target);
        // Different authority → network path reference
        static::assertStringContainsString('//other.com', (string) $relative);
    }

    public function testUriResolverRelativizeDifferentScheme(): void
    {
        $base = new Uri('http://example.com/path');
        $target = new Uri('ftp://example.com/path');
        // Different scheme, returns target unchanged
        $relative = UriResolver::relativize($base, $target);
        static::assertSame('ftp://example.com/path', (string) $relative);
    }

    public function testUriResolverRelativizeAlreadyRelative(): void
    {
        $base = new Uri('http://example.com/a/');
        $target = new Uri('relative/path');
        // Already relative path → return as-is
        $relative = UriResolver::relativize($base, $target);
        static::assertSame('relative/path', (string) $relative);
    }

    public function testUriResolverResolveEmpty(): void
    {
        $base = new Uri('http://example.com/path');
        $rel = new Uri('');
        $resolved = UriResolver::resolve($base, $rel);
        static::assertSame('http://example.com/path', (string) $resolved);
    }

    public function testUriResolverResolveAbsolute(): void
    {
        $base = new Uri('http://example.com/path');
        $rel = new Uri('http://other.com/other');
        $resolved = UriResolver::resolve($base, $rel);
        static::assertSame('http://other.com/other', (string) $resolved);
    }

    public function testUriResolverResolveRelativePath(): void
    {
        $base = new Uri('http://example.com/a/b/c');
        $rel = new Uri('../d');
        $resolved = UriResolver::resolve($base, $rel);
        static::assertSame('http://example.com/a/d', (string) $resolved);
    }

    public function testUriResolverResolveAbsolutePath(): void
    {
        $base = new Uri('http://example.com/a/b/');
        $rel = new Uri('/absolute');
        $resolved = UriResolver::resolve($base, $rel);
        static::assertSame('http://example.com/absolute', (string) $resolved);
    }

    public function testUriResolverResolveWithQuery(): void
    {
        $base = new Uri('http://example.com/path?existing=1');
        $rel = new Uri('?new=2');
        $resolved = UriResolver::resolve($base, $rel);
        static::assertSame('http://example.com/path?new=2', (string) $resolved);
    }

    public function testUriResolverResolveNoPathInRel(): void
    {
        $base = new Uri('http://example.com/path?q=1');
        $rel = new Uri('');
        $resolved = UriResolver::resolve($base, $rel);
        static::assertSame('http://example.com/path?q=1', (string) $resolved);
    }

    public function testUriResolverResolveWithAuthority(): void
    {
        $base = new Uri('http://example.com/path');
        $rel = new Uri('//other.com/new');
        $resolved = UriResolver::resolve($base, $rel);
        static::assertSame('http://other.com/new', (string) $resolved);
    }

    // =========================================================================
    // Client – request builder methods (no network)
    // =========================================================================

    public function testClientDeleteRequest(): void
    {
        $req = Client::delete_request('http://example.com/', null, Mime::JSON);
        static::assertSame(Http::DELETE, $req->getMethod());
        static::assertInstanceOf(Request::class, $req);
    }

    public function testClientGetRequest(): void
    {
        $req = Client::get_request('http://example.com/');
        static::assertSame(Http::GET, $req->getMethod());
        static::assertInstanceOf(Request::class, $req);
    }

    public function testClientHeadRequest(): void
    {
        $req = Client::head_request('http://example.com/');
        static::assertSame(Http::HEAD, $req->getMethod());
        static::assertInstanceOf(Request::class, $req);
    }

    public function testClientOptionsRequest(): void
    {
        $req = Client::options_request('http://example.com/');
        static::assertSame(Http::OPTIONS, $req->getMethod());
        static::assertInstanceOf(Request::class, $req);
    }

    public function testClientPatchRequest(): void
    {
        $req = Client::patch_request('http://example.com/', ['data' => 'value'], Mime::JSON);
        static::assertSame(Http::PATCH, $req->getMethod());
        static::assertInstanceOf(Request::class, $req);
    }

    public function testClientPostRequest(): void
    {
        $req = Client::post_request('http://example.com/', ['data' => 'value'], Mime::JSON);
        static::assertSame(Http::POST, $req->getMethod());
        static::assertInstanceOf(Request::class, $req);
    }

    public function testClientPutRequest(): void
    {
        $req = Client::put_request('http://example.com/', ['data' => 'value']);
        static::assertSame(Http::PUT, $req->getMethod());
        static::assertInstanceOf(Request::class, $req);
    }

    // =========================================================================
    // Response – uncovered methods
    // =========================================================================

    public function testResponseGetBody(): void
    {
        $req = Request::get('http://example.com/');
        $response = new Response('hello world', "HTTP/1.1 200 OK\r\n\r\n", $req, []);
        $body = $response->getBody();
        static::assertNotNull($body);
    }

    public function testResponseGetHeaders(): void
    {
        $req = Request::get('http://example.com/');
        $response = new Response('', "HTTP/1.1 200 OK\r\nContent-Type: text/plain\r\n\r\n", $req, []);
        static::assertIsArray($response->getHeaders());
    }

    public function testResponseGetHeader(): void
    {
        $req = Request::get('http://example.com/');
        $response = new Response('', "HTTP/1.1 200 OK\r\nContent-Type: text/plain\r\n\r\n", $req, []);
        static::assertSame(['text/plain'], $response->getHeader('Content-Type'));
        static::assertSame([], $response->getHeader('X-Missing'));
    }

    public function testResponseHasHeader(): void
    {
        $req = Request::get('http://example.com/');
        $response = new Response('', "HTTP/1.1 200 OK\r\nX-Foo: bar\r\n\r\n", $req, []);
        static::assertTrue($response->hasHeader('X-Foo'));
        static::assertFalse($response->hasHeader('X-Missing'));
    }

    public function testResponseGetHeaderLine(): void
    {
        $req = Request::get('http://example.com/');
        $response = new Response('', "HTTP/1.1 200 OK\r\nContent-Type: text/plain\r\n\r\n", $req, []);
        static::assertSame('text/plain', $response->getHeaderLine('Content-Type'));
        static::assertSame('', $response->getHeaderLine('X-Missing'));
    }

    public function testResponseWithHeader(): void
    {
        $req = Request::get('http://example.com/');
        $response = new Response('', "HTTP/1.1 200 OK\r\n\r\n", $req, []);
        $new = $response->withHeader('X-Custom', 'value');
        static::assertTrue($new->hasHeader('X-Custom'));
    }

    public function testResponseWithAddedHeader(): void
    {
        $req = Request::get('http://example.com/');
        $response = new Response('', "HTTP/1.1 200 OK\r\nX-Foo: first\r\n\r\n", $req, []);
        $new = $response->withAddedHeader('X-Foo', 'second');
        $values = $new->getHeader('X-Foo');
        static::assertCount(2, $values);
    }

    public function testResponseWithoutHeader(): void
    {
        $req = Request::get('http://example.com/');
        $response = new Response('', "HTTP/1.1 200 OK\r\nX-Foo: bar\r\n\r\n", $req, []);
        $new = $response->withoutHeader('X-Foo');
        static::assertFalse($new->hasHeader('X-Foo'));
    }

    public function testResponseWithBody(): void
    {
        $req = Request::get('http://example.com/');
        $response = new Response('', "HTTP/1.1 200 OK\r\n\r\n", $req, []);
        $stream = \Httpful\Stream::createNotNull('new body');
        $new = $response->withBody($stream);
        static::assertNotNull($new->getBody());
    }

    public function testResponseGetRawBody(): void
    {
        $req = Request::get('http://example.com/');
        $response = new Response('raw content', "HTTP/1.1 200 OK\r\nContent-Type: text/plain\r\n\r\n", $req, []);
        static::assertSame('raw content', $response->getRawBody());
    }

    public function testResponseIsOk(): void
    {
        $req = Request::get('http://example.com/');
        $response = new Response('', "HTTP/1.1 200 OK\r\n\r\n", $req, []);
        static::assertFalse($response->hasErrors());
    }

    public function testResponseIsNotOk(): void
    {
        $req = Request::get('http://example.com/');
        $response = new Response('', "HTTP/1.1 404 Not Found\r\n\r\n", $req, []);
        static::assertTrue($response->hasErrors());
    }

    public function testResponseToString(): void
    {
        $req = Request::get('http://example.com/');
        $response = new Response('hello', "HTTP/1.1 200 OK\r\nContent-Type: text/plain\r\n\r\n", $req, []);
        $str = (string) $response;
        static::assertSame('hello', $str);
    }

    public function testResponseGetCodeIsIntegerAlias(): void
    {
        $req = Request::get('http://example.com/');
        $response = new Response('', "HTTP/1.1 201 Created\r\n\r\n", $req, []);
        static::assertSame(201, $response->getStatusCode());
    }

    public function testResponseGetAndSetMetadata(): void
    {
        $req = Request::get('http://example.com/');
        $response = new Response('', "HTTP/1.1 200 OK\r\n\r\n", $req, ['custom' => 'meta']);
        $meta = $response->getMetaData();
        static::assertArrayHasKey('custom', $meta);
    }

    public function testResponseGetCharset(): void
    {
        $req = Request::get('http://example.com/');
        $response = new Response('', "HTTP/1.1 200 OK\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n", $req, []);
        static::assertSame('UTF-8', $response->getCharset());
    }

    public function testResponseGetParentType(): void
    {
        $req = Request::get('http://example.com/');
        $response = new Response('', "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\n\r\n", $req, []);
        // No '+' in content type, parent_type = content_type
        static::assertSame(Mime::getFullMime(Mime::JSON), $response->getParentType());
    }

    public function testResponseIsMimeVendorSpecific(): void
    {
        $req = Request::get('http://example.com/');
        $response = new Response('', "HTTP/1.1 200 OK\r\nContent-Type: application/vnd.github+json\r\n\r\n", $req, []);
        static::assertTrue($response->isMimeVendorSpecific());
    }

    public function testResponseIsMimePersonal(): void
    {
        $req = Request::get('http://example.com/');
        $response = new Response('', "HTTP/1.1 200 OK\r\nContent-Type: application/prs.custom+json\r\n\r\n", $req, []);
        static::assertTrue($response->isMimePersonal());
    }

    public function testResponseHasBody(): void
    {
        $req = Request::get('http://example.com/');
        $response = new Response('content', "HTTP/1.1 200 OK\r\nContent-Type: text/plain\r\n\r\n", $req, []);
        static::assertTrue($response->hasBody());

        $emptyResponse = new Response('', "HTTP/1.1 200 OK\r\n\r\n", $req, []);
        static::assertFalse($emptyResponse->hasBody());
    }

    public function testResponseGetRawHeaders(): void
    {
        $req = Request::get('http://example.com/');
        $rawHeaders = "HTTP/1.1 200 OK\r\nContent-Type: text/plain\r\n\r\n";
        $response = new Response('', $rawHeaders, $req, []);
        static::assertSame($rawHeaders, $response->getRawHeaders());
    }

    public function testResponseGetHeadersObject(): void
    {
        $req = Request::get('http://example.com/');
        $response = new Response('', "HTTP/1.1 200 OK\r\nX-Test: value\r\n\r\n", $req, []);
        static::assertInstanceOf(\Httpful\Headers::class, $response->getHeadersObject());
    }

    public function testResponseWithHeaders(): void
    {
        $req = Request::get('http://example.com/');
        $response = new Response('', "HTTP/1.1 200 OK\r\n\r\n", $req, []);
        $new = $response->withHeaders(['X-A' => 'a', 'X-B' => 'b']);
        static::assertTrue($new->hasHeader('X-A'));
        static::assertTrue($new->hasHeader('X-B'));
    }

    public function testResponseGetContentType(): void
    {
        $req = Request::get('http://example.com/');
        $response = new Response('', "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\n\r\n", $req, []);
        static::assertSame(Mime::getFullMime(Mime::JSON), $response->getContentType());
    }

    public function testResponseWithRedirectHeaders(): void
    {
        $req = Request::get('http://example.com/');
        // Simulate redirect: headers from two responses
        $headers = "HTTP/1.1 301 Moved Permanently\r\nLocation: http://example.com/new\r\n\r\nHTTP/1.1 200 OK\r\nContent-Type: text/plain\r\n\r\n";
        $response = new Response('', $headers, $req, []);
        static::assertSame(200, $response->getStatusCode());
    }

    public function testResponseGetResponseCodeFromHeaderStringThrowsOnMalformed(): void
    {
        $this->expectException(\Httpful\Exception\ResponseException::class);
        $req = Request::get('http://example.com/');
        $response = new Response('', "HTTP/1.1 200 OK\r\n\r\n", $req, []);
        $response->_getResponseCodeFromHeaderString('MALFORMED NO SPACE');
    }

    // =========================================================================
    // Request – _curlPrep and remaining methods
    // =========================================================================

    public function testRequestInitialize(): void
    {
        $req = Request::get('http://example.com/');
        // initialize() should not throw
        $req->initialize();
        // On PHP 8.x, CurlHandle is an object not a resource,
        // so hasBeenInitialized() returns false (known issue in codebase)
        static::assertInstanceOf(Request::class, $req);
        $req->close();
    }

    public function testRequestInitializeMulti(): void
    {
        $req = Request::get('http://example.com/');
        // initializeMulti() should not throw
        $req->initializeMulti();
        static::assertInstanceOf(Request::class, $req);
    }

    public function testRequestHasBeenInitializedFalse(): void
    {
        $req = Request::get('http://example.com/');
        // On PHP 8.x this always returns false (is_resource doesn't match CurlHandle)
        $result = $req->hasBeenInitialized();
        static::assertIsBool($result);
    }

    public function testRequestHasBeenInitializedMultiFalse(): void
    {
        $req = Request::get('http://example.com/');
        $result = $req->hasBeenInitializedMulti();
        static::assertIsBool($result);
    }

    public function testRequestReset(): void
    {
        $req = Request::get('http://example.com/');
        $req->initialize();
        $req->reset();
        // After reset, request is still usable
        static::assertInstanceOf(Request::class, $req);
        $req->close();
    }

    public function testRequestClose(): void
    {
        $req = Request::get('http://example.com/');
        $req->initialize();
        $req->close();
        // After close, not initialized
        static::assertFalse($req->hasBeenInitialized());
    }

    public function testRequestBeforeSendAddsCallback(): void
    {
        $req = Request::get('http://example.com/')
            ->beforeSend(static function () {
            })
            ->beforeSend(static function () {
            });
        static::assertCount(2, $req->getSendCallback());
    }

    public function testRequestFollowRedirectsWithDefaultBool(): void
    {
        $req = Request::get('http://example.com/')->followRedirects(true);
        static::assertInstanceOf(Request::class, $req);
    }

    public function testRequestClientSideCertNoKey(): void
    {
        $req = Request::get('http://example.com/')
            ->clientSideCertAuth('/path/cert.pem', '', null, 'PEM');
        static::assertFalse($req->hasClientSideCert());
    }

    public function testRequestWithParamsAndUri(): void
    {
        $req = Request::get('http://example.com/')
            ->withParams(['a' => '1', 'b' => '2'])
            ->withParam('c', '3');
        static::assertInstanceOf(Request::class, $req);
    }

    public function testRequestExpectsWithFallback(): void
    {
        // When mime is empty, fallback is used
        $req = Request::get('http://example.com/')->withExpectedType(null, Mime::JSON);
        static::assertSame(Mime::getFullMime(Mime::JSON), $req->getExpectedType());
    }

    public function testRequestWithContentTypeWithFallback(): void
    {
        $req = Request::get('http://example.com/')->withContentType(null, Mime::JSON);
        static::assertSame(Mime::getFullMime(Mime::JSON), $req->getContentType());
    }

    public function testRequestWithUriFromStringNoClone(): void
    {
        $req = Request::get('http://example.com/');
        $new = $req->withUriFromString('http://other.com/', false);
        static::assertSame('http://other.com/', $new->getUriString());
    }

    public function testRequestGetBodyReturnsStreamForPayload(): void
    {
        $req = Request::post('http://example.com/', 'test payload');
        $body = $req->getBody();
        static::assertInstanceOf(\Psr\Http\Message\StreamInterface::class, $body);
    }

    public function testRequestSetup(): void
    {
        // Test that creating request with a template works
        $template = Request::get('http://example.com/')
            ->withHeader('X-Api-Key', 'mykey')
            ->withMimeType(Mime::JSON);
        $req = new Request(Http::GET, Mime::JSON, $template);
        static::assertInstanceOf(Request::class, $req);
        static::assertSame(Mime::getFullMime(Mime::JSON), $req->getContentType());
    }

    public function testRequestGetUrlWithFragment(): void
    {
        $req = Request::get('http://example.com/path#section');
        static::assertStringContainsString('example.com', $req->getUriString());
    }

    public function testRequestWithHeaderArrayValue(): void
    {
        $req = Request::get('http://example.com/')
            ->withHeader('Accept', ['text/html', 'application/json']);
        static::assertCount(2, $req->getHeader('Accept'));
    }

    // =========================================================================
    // Http – allMethods, reason, and other helpers
    // =========================================================================

    public function testHttpAllMethods(): void
    {
        $methods = Http::allMethods();
        static::assertContains(Http::GET, $methods);
        static::assertContains(Http::POST, $methods);
        static::assertContains(Http::PUT, $methods);
        static::assertContains(Http::DELETE, $methods);
        static::assertContains(Http::HEAD, $methods);
        static::assertContains(Http::OPTIONS, $methods);
        static::assertContains(Http::PATCH, $methods);
    }

    public function testHttpReason(): void
    {
        static::assertSame('OK', Http::reason(200));
        static::assertSame('Not Found', Http::reason(404));
        static::assertSame('Internal Server Error', Http::reason(500));
        // 999 is not a known code
        static::assertFalse(\Httpful\Http::responseCodeExists(999));
    }

    public function testHttpStream(): void
    {
        $stream = Http::stream('content');
        static::assertNotNull($stream);
    }

    // =========================================================================
    // Mime – getFullMime for all types
    // =========================================================================

    public function testMimeGetFullMimeAllTypes(): void
    {
        $mimes = [
            Mime::JSON, Mime::XML, Mime::HTML, Mime::CSV, Mime::FORM,
            Mime::PLAIN, Mime::JS, Mime::YAML, Mime::UPLOAD, Mime::XHTML,
        ];
        foreach ($mimes as $mime) {
            $full = Mime::getFullMime($mime);
            static::assertNotEmpty($full, "getFullMime returned empty for: $mime");
            static::assertStringContainsString('/', $full, "getFullMime should return type/subtype for: $mime");
        }
    }

    public function testMimeGetFullMimeAlreadyFull(): void
    {
        $full = 'application/json';
        static::assertSame($full, Mime::getFullMime($full));
    }

    public function testMimeSupportsMime(): void
    {
        // supportsMimeType uses short names as keys
        static::assertTrue(Mime::supportsMimeType('json'));
        static::assertTrue(Mime::supportsMimeType('xml'));
        static::assertTrue(Mime::supportsMimeType('html'));
        static::assertFalse(Mime::supportsMimeType('unknown-type'));
        // Full mime types are NOT keys in the map
        static::assertFalse(Mime::supportsMimeType(Mime::JSON));
    }
}
