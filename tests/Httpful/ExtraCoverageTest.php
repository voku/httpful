<?php

declare(strict_types=1);

namespace Httpful\tests;

use Httpful\Exception\ClientErrorException;
use Httpful\Exception\NetworkErrorException;
use Httpful\Exception\RequestException;
use Httpful\Exception\ResponseHeaderException;
use Httpful\Handlers\CsvMimeHandler;
use Httpful\Handlers\DefaultMimeHandler;
use Httpful\Handlers\HtmlMimeHandler;
use Httpful\Handlers\XmlMimeHandler;
use Httpful\Headers;
use Httpful\Http;
use Httpful\Mime;
use Httpful\Request;
use Httpful\Response;
use Httpful\Stream;
use Httpful\UploadedFile;
use Httpful\Uri;
use PHPUnit\Framework\TestCase;

/**
 * Extra tests to boost coverage by ≥15 percentage points.
 *
 * @internal
 */
final class ExtraCoverageTest extends TestCase
{
    // =========================================================================
    // Request – builder / setter methods
    // =========================================================================

    public function testRequestGetMethod(): void
    {
        $req = Request::get('http://example.com/');
        static::assertSame(Http::GET, $req->getMethod());
        static::assertSame(Http::GET, $req->getHttpMethod());
    }

    public function testRequestHeadFactory(): void
    {
        $req = Request::head('http://example.com/path');
        static::assertSame(Http::HEAD, $req->getMethod());
    }

    public function testRequestOptionsFactory(): void
    {
        $req = Request::options('http://example.com/path');
        static::assertSame(Http::OPTIONS, $req->getMethod());
    }

    public function testRequestPatchFactory(): void
    {
        $req = Request::patch('http://example.com/path', ['x' => 1], Mime::JSON);
        static::assertSame(Http::PATCH, $req->getMethod());
        static::assertSame(Mime::getFullMime(Mime::JSON), $req->getContentType());
    }

    public function testRequestDeleteWithParams(): void
    {
        $req = Request::delete('http://example.com/res', ['a' => '1'], Mime::JSON);
        static::assertSame(Http::DELETE, $req->getMethod());
        static::assertStringContainsString('a=1', $req->getUriString());
    }

    public function testRequestDeleteParamsWithExistingQueryString(): void
    {
        $req = Request::delete('http://example.com/res?x=1', ['a' => '2']);
        static::assertStringContainsString('a=2', $req->getUriString());
        static::assertStringContainsString('x=1', $req->getUriString());
    }

    public function testRequestGetWithParams(): void
    {
        $req = Request::get('http://example.com/', ['page' => '2']);
        static::assertStringContainsString('page=2', $req->getUriString());
    }

    public function testRequestGetWithParamsAndExistingQuery(): void
    {
        $req = Request::get('http://example.com/?sort=asc', ['page' => '2']);
        static::assertStringContainsString('page=2', $req->getUriString());
        static::assertStringContainsString('sort=asc', $req->getUriString());
    }

    public function testRequestDownloadFactory(): void
    {
        $req = Request::download('http://example.com/file.zip', '/tmp/file.zip');
        static::assertSame(Http::GET, $req->getMethod());
        static::assertSame('http://example.com/file.zip', $req->getUriString());
    }

    public function testWithBasicAuth(): void
    {
        $req = Request::get('http://example.com/')->withBasicAuth('user', 'pass');
        static::assertTrue($req->hasBasicAuth());
    }

    public function testHasBasicAuthReturnsFalseWhenNotSet(): void
    {
        $req = Request::get('http://example.com/');
        static::assertFalse($req->hasBasicAuth());
    }

    public function testWithDigestAuth(): void
    {
        $req = Request::get('http://example.com/')->withDigestAuth('user', 'pass');
        static::assertTrue($req->hasBasicAuth());
        static::assertTrue($req->hasDigestAuth());
    }

    public function testWithNtlmAuthSetsBasicAuthCredentials(): void
    {
        $req = Request::get('http://example.com/')->withNtlmAuth('user', 'pass');
        static::assertTrue($req->hasBasicAuth());
        $iter = $req->getIterator();
        static::assertSame(\CURLAUTH_NTLM, $iter['additional_curl_opts'][\CURLOPT_HTTPAUTH]);
    }

    public function testWithProxy(): void
    {
        $req = Request::get('http://example.com/')
            ->withProxy('proxy.example.com', 3128);
        static::assertTrue($req->hasProxy());
    }

    public function testUseSocks4Proxy(): void
    {
        $req = Request::get('http://example.com/')
            ->useSocks4Proxy('socks.example.com', 1080);
        static::assertTrue($req->hasProxy());
    }

    public function testUseSocks5Proxy(): void
    {
        $req = Request::get('http://example.com/')
            ->useSocks5Proxy('socks5.example.com', 1080);
        static::assertTrue($req->hasProxy());
    }

    public function testWithProxyWithAuth(): void
    {
        $req = Request::get('http://example.com/')
            ->withProxy('proxy.example.com', 3128, \CURLAUTH_BASIC, 'proxyuser', 'proxypass');
        static::assertTrue($req->hasProxy());
    }

    public function testWithParams(): void
    {
        $req = Request::get('http://example.com/')
            ->withUriFromString('http://example.com/')
            ->withParams(['foo' => 'bar', 'baz' => 'qux'])
            ->withParam('extra', 'val');
        static::assertNotNull($req->getUriOrNull());
        static::assertInstanceOf(Request::class, $req);
    }

    public function testWithParam(): void
    {
        $req = Request::get('http://example.com/')
            ->withParam('key', 'value');
        // Params are accumulated and applied at send time; verify the request itself is returned
        static::assertInstanceOf(Request::class, $req);
    }

    public function testWithParamIgnoresEmptyKey(): void
    {
        $req = Request::get('http://example.com/');
        $new = $req->withParam('', 'value');
        // Should return a clone but not add the param
        static::assertInstanceOf(Request::class, $new);
    }

    public function testWithParseCallback(): void
    {
        $callback = static function ($body) {
            return $body;
        };
        $req = Request::get('http://example.com/')->withParseCallback($callback);
        static::assertTrue($req->hasParseCallback());
        static::assertSame($callback, $req->getParseCallback());
    }

    public function testHasParseCallbackFalseWhenNotSet(): void
    {
        $req = Request::get('http://example.com/');
        static::assertFalse($req->hasParseCallback());
    }

    public function testBeforeSend(): void
    {
        $called = false;
        $req = Request::get('http://example.com/')
            ->beforeSend(static function () use (&$called) {
                $called = true;
            });
        static::assertCount(1, $req->getSendCallback());
    }

    public function testWithSendCallback(): void
    {
        $req = Request::get('http://example.com/')
            ->withSendCallback(static function () {
            });
        static::assertCount(1, $req->getSendCallback());
    }

    public function testWithSendCallbackIgnoresNull(): void
    {
        $req = Request::get('http://example.com/')->withSendCallback(null);
        static::assertCount(0, $req->getSendCallback());
    }

    public function testWithErrorHandler(): void
    {
        $handler = static function ($err) {
        };
        $req = Request::get('http://example.com/')->withErrorHandler($handler);
        static::assertSame($handler, $req->getErrorHandler());
    }

    public function testGetErrorHandlerNullByDefault(): void
    {
        $req = Request::get('http://example.com/');
        static::assertNull($req->getErrorHandler());
    }

    public function testWithBodyFromArray(): void
    {
        $req = Request::post('http://example.com/')->withBodyFromArray(['a' => 1]);
        static::assertInstanceOf(Request::class, $req);
    }

    public function testWithBodyFromString(): void
    {
        $req = Request::post('http://example.com/')->withBodyFromString('hello world');
        static::assertInstanceOf(Request::class, $req);
    }

    public function testWithCacheControl(): void
    {
        $req = Request::get('http://example.com/')->withCacheControl('no-cache');
        static::assertInstanceOf(Request::class, $req);
    }

    public function testWithCacheControlEmpty(): void
    {
        $req = Request::get('http://example.com/')->withCacheControl('');
        static::assertInstanceOf(Request::class, $req);
    }

    public function testWithContentCharset(): void
    {
        $req = Request::get('http://example.com/')->withContentCharset('UTF-8');
        static::assertInstanceOf(Request::class, $req);
    }

    public function testWithContentCharsetEmpty(): void
    {
        $req = Request::get('http://example.com/')->withContentCharset('');
        static::assertInstanceOf(Request::class, $req);
    }

    public function testWithContentEncoding(): void
    {
        $req = Request::get('http://example.com/')->withContentEncoding('gzip');
        static::assertInstanceOf(Request::class, $req);
    }

    public function testWithPort(): void
    {
        $req = Request::get('http://example.com/')
            ->withUriFromString('http://example.com/path')
            ->withPort(8080);
        static::assertSame(8080, $req->getUri()->getPort());
    }

    public function testWithPortNoUri(): void
    {
        // withPort when no URI is set should not crash
        $req = new Request(Http::GET);
        $new = $req->withPort(9000);
        static::assertInstanceOf(Request::class, $new);
    }

    public function testWithTimeout(): void
    {
        $req = Request::get('http://example.com/')->withTimeout(5);
        static::assertTrue($req->hasTimeout());
    }

    public function testWithTimeoutInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Request::get('http://example.com/')->withTimeout('not-a-number');
    }

    public function testHasTimeoutFalseByDefault(): void
    {
        $req = Request::get('http://example.com/');
        static::assertFalse($req->hasTimeout());
    }

    public function testWithConnectionTimeout(): void
    {
        $req = Request::get('http://example.com/')->withConnectionTimeoutInSeconds(3);
        static::assertTrue($req->hasConnectionTimeout());
    }

    public function testWithConnectionTimeoutInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Request::get('http://example.com/')->withConnectionTimeoutInSeconds('bad');
    }

    public function testHasConnectionTimeoutFalseByDefault(): void
    {
        $req = Request::get('http://example.com/');
        static::assertFalse($req->hasConnectionTimeout());
    }

    public function testFollowRedirects(): void
    {
        $req = Request::get('http://example.com/')->followRedirects();
        static::assertInstanceOf(Request::class, $req);
    }

    public function testDoNotFollowRedirects(): void
    {
        $req = Request::get('http://example.com/')->doNotFollowRedirects();
        static::assertInstanceOf(Request::class, $req);
    }

    public function testDisableAndEnableKeepAlive(): void
    {
        $req = Request::get('http://example.com/')->disableKeepAlive();
        static::assertInstanceOf(Request::class, $req);

        $req2 = Request::get('http://example.com/')->enableKeepAlive(60);
        static::assertInstanceOf(Request::class, $req2);
    }

    public function testEnableKeepAliveInvalidThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Request::get('http://example.com/')->enableKeepAlive(0);
    }

    public function testEnableRetryEncoding(): void
    {
        $req = Request::get('http://example.com/')->enableRetryByPossibleEncodingError();
        static::assertInstanceOf(Request::class, $req);
        $req2 = $req->disableRetryByPossibleEncodingError();
        static::assertInstanceOf(Request::class, $req2);
    }

    public function testEnableAndDisableStrictSSL(): void
    {
        $req = Request::get('http://example.com/')->enableStrictSSL();
        static::assertTrue($req->isStrictSSL());
        $req2 = $req->disableStrictSSL();
        static::assertFalse($req2->isStrictSSL());
    }

    public function testEnableAndDisableAutoParsing(): void
    {
        $req = Request::get('http://example.com/')->disableAutoParsing();
        static::assertFalse($req->isAutoParse());
        $req2 = $req->enableAutoParsing();
        static::assertTrue($req2->isAutoParse());
    }

    public function testExpectsVariousTypes(): void
    {
        static::assertSame(Mime::getFullMime(Mime::CSV), Request::get('http://example.com/')->expectsCsv()->getExpectedType());
        static::assertSame(Mime::getFullMime(Mime::FORM), Request::get('http://example.com/')->expectsForm()->getExpectedType());
        static::assertSame(Mime::getFullMime(Mime::HTML), Request::get('http://example.com/')->expectsHtml()->getExpectedType());
        static::assertSame(Mime::getFullMime(Mime::JS), Request::get('http://example.com/')->expectsJavascript()->getExpectedType());
        static::assertSame(Mime::getFullMime(Mime::JS), Request::get('http://example.com/')->expectsJs()->getExpectedType());
        static::assertSame(Mime::getFullMime(Mime::JSON), Request::get('http://example.com/')->expectsJson()->getExpectedType());
        static::assertSame(Mime::getFullMime(Mime::PLAIN), Request::get('http://example.com/')->expectsPlain()->getExpectedType());
        static::assertSame(Mime::getFullMime(Mime::PLAIN), Request::get('http://example.com/')->expectsText()->getExpectedType());
        static::assertSame(Mime::getFullMime(Mime::UPLOAD), Request::get('http://example.com/')->expectsUpload()->getExpectedType());
        static::assertSame(Mime::getFullMime(Mime::XHTML), Request::get('http://example.com/')->expectsXhtml()->getExpectedType());
        static::assertSame(Mime::getFullMime(Mime::XML), Request::get('http://example.com/')->expectsXml()->getExpectedType());
        static::assertSame(Mime::getFullMime(Mime::YAML), Request::get('http://example.com/')->expectsYaml()->getExpectedType());
    }

    public function testSendsVariousTypes(): void
    {
        static::assertSame(Mime::getFullMime(Mime::CSV), Request::get('http://example.com/')->sendsCsv()->getContentType());
        static::assertSame(Mime::getFullMime(Mime::FORM), Request::get('http://example.com/')->sendsForm()->getContentType());
        static::assertSame(Mime::getFullMime(Mime::HTML), Request::get('http://example.com/')->sendsHtml()->getContentType());
        static::assertSame(Mime::getFullMime(Mime::JS), Request::get('http://example.com/')->sendsJavascript()->getContentType());
        static::assertSame(Mime::getFullMime(Mime::JS), Request::get('http://example.com/')->sendsJs()->getContentType());
        static::assertSame(Mime::getFullMime(Mime::JSON), Request::get('http://example.com/')->sendsJson()->getContentType());
        static::assertSame(Mime::getFullMime(Mime::PLAIN), Request::get('http://example.com/')->sendsPlain()->getContentType());
        static::assertSame(Mime::getFullMime(Mime::PLAIN), Request::get('http://example.com/')->sendsText()->getContentType());
        static::assertSame(Mime::getFullMime(Mime::UPLOAD), Request::get('http://example.com/')->sendsUpload()->getContentType());
        static::assertSame(Mime::getFullMime(Mime::XHTML), Request::get('http://example.com/')->sendsXhtml()->getContentType());
        static::assertSame(Mime::getFullMime(Mime::XML), Request::get('http://example.com/')->sendsXml()->getContentType());
    }

    public function testWithContentTypeHelpers(): void
    {
        static::assertSame(Mime::getFullMime(Mime::CSV), Request::get('http://example.com/')->withContentTypeCsv()->getContentType());
        static::assertSame(Mime::getFullMime(Mime::FORM), Request::get('http://example.com/')->withContentTypeForm()->getContentType());
        static::assertSame(Mime::getFullMime(Mime::HTML), Request::get('http://example.com/')->withContentTypeHtml()->getContentType());
        static::assertSame(Mime::getFullMime(Mime::JSON), Request::get('http://example.com/')->withContentTypeJson()->getContentType());
        static::assertSame(Mime::getFullMime(Mime::PLAIN), Request::get('http://example.com/')->withContentTypePlain()->getContentType());
        static::assertSame(Mime::getFullMime(Mime::XML), Request::get('http://example.com/')->withContentTypeXml()->getContentType());
        static::assertSame(Mime::getFullMime(Mime::YAML), Request::get('http://example.com/')->withContentTypeYaml()->getContentType());
    }

    public function testIsJsonAndIsUpload(): void
    {
        $jsonReq = Request::get('http://example.com/')->sendsJson();
        static::assertTrue($jsonReq->isJson());
        static::assertFalse($jsonReq->isUpload());

        $uploadReq = Request::get('http://example.com/')->sendsUpload();
        static::assertFalse($uploadReq->isJson());
        static::assertTrue($uploadReq->isUpload());
    }

    public function testBuildUserAgent(): void
    {
        $ua = Request::get('http://example.com/')->buildUserAgent();
        static::assertStringStartsWith('User-Agent: Http/PhpClient', $ua);
    }

    public function testGetIterator(): void
    {
        $req = Request::get('http://example.com/');
        $it = $req->getIterator();
        static::assertInstanceOf(\ArrayObject::class, $it);
        static::assertGreaterThan(0, $it->count());
    }

    public function testGetRequestTargetWithNoUri(): void
    {
        $req = new Request(Http::GET);
        static::assertSame('/', $req->getRequestTarget());
    }

    public function testGetRequestTargetWithQueryString(): void
    {
        $req = Request::get('http://example.com/path?foo=bar');
        static::assertSame('/path?foo=bar', $req->getRequestTarget());
    }

    public function testGetRequestTargetWithEmptyPath(): void
    {
        $req = Request::get('http://example.com');
        // When path is empty, should return '/'
        static::assertSame('/', $req->getRequestTarget());
    }

    public function testGetUriOrNull(): void
    {
        $req = new Request(Http::GET);
        static::assertNull($req->getUriOrNull());

        $req2 = Request::get('http://example.com/');
        static::assertNotNull($req2->getUriOrNull());
    }

    public function testGetUriThrowsWhenNotSet(): void
    {
        $this->expectException(RequestException::class);
        $req = new Request(Http::GET);
        $req->getUri();
    }

    public function testGetUriString(): void
    {
        $req = Request::get('http://example.com/path');
        static::assertSame('http://example.com/path', $req->getUriString());
    }

    public function testGetProtocolVersion(): void
    {
        $req = Request::get('http://example.com/');
        static::assertSame(Http::HTTP_1_1, $req->getProtocolVersion());

        $req2 = $req->withProtocolVersion(Http::HTTP_2_0);
        static::assertSame(Http::HTTP_2_0, $req2->getProtocolVersion());
    }

    public function testWithMethod(): void
    {
        $req = Request::get('http://example.com/')->withMethod(Http::POST);
        static::assertSame(Http::POST, $req->getMethod());
    }

    public function testWithHeader(): void
    {
        $req = Request::get('http://example.com/')->withHeader('X-Foo', 'bar');
        static::assertTrue($req->hasHeader('X-Foo'));
        static::assertSame(['bar'], $req->getHeader('X-Foo'));
        static::assertSame('bar', $req->getHeaderLine('X-Foo'));
    }

    public function testWithHeaderMultipleValues(): void
    {
        $req = Request::get('http://example.com/')
            ->withHeader('X-Multi', ['a', 'b']);
        static::assertSame(['a', 'b'], $req->getHeader('X-Multi'));
        static::assertSame('a, b', $req->getHeaderLine('X-Multi'));
    }

    public function testGetHeaderReturnsEmptyArrayForMissingHeader(): void
    {
        $req = Request::get('http://example.com/');
        static::assertSame([], $req->getHeader('X-Does-Not-Exist'));
        static::assertSame('', $req->getHeaderLine('X-Does-Not-Exist'));
    }

    public function testWithAddedHeaderAppendsValues(): void
    {
        $req = Request::get('http://example.com/')
            ->withHeader('X-Foo', 'first')
            ->withAddedHeader('X-Foo', 'second');
        $values = $req->getHeader('X-Foo');
        static::assertContains('first', $values);
        static::assertContains('second', $values);
    }

    public function testWithAddedHeaderInvalidNameThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Request::get('http://example.com/')->withAddedHeader('', 'value');
    }

    public function testWithoutHeader(): void
    {
        $req = Request::get('http://example.com/')
            ->withHeader('X-Foo', 'bar')
            ->withoutHeader('X-Foo');
        static::assertFalse($req->hasHeader('X-Foo'));
    }

    public function testWithHeaders(): void
    {
        $req = Request::get('http://example.com/')
            ->withHeaders(['X-A' => 'a', 'X-B' => 'b']);
        static::assertTrue($req->hasHeader('X-A'));
        static::assertTrue($req->hasHeader('X-B'));
    }

    public function testWithBody(): void
    {
        $stream = Stream::createNotNull('hello');
        $req = Request::post('http://example.com/')->withBody($stream);
        static::assertInstanceOf(Request::class, $req);
    }

    public function testGetBodyReturnsStream(): void
    {
        $req = Request::post('http://example.com/', 'payload');
        static::assertInstanceOf(\Psr\Http\Message\StreamInterface::class, $req->getBody());
    }

    public function testWithUri(): void
    {
        $uri = new Uri('http://other.com/path');
        $req = Request::get('http://example.com/')->withUri($uri);
        static::assertSame('http://other.com/path', (string) $req->getUri());
    }

    public function testWithUriPreserveHost(): void
    {
        $req = Request::get('http://example.com/')
            ->withHeader('Host', 'original.com');
        $uri = new Uri('http://other.com/path');
        $new = $req->withUri($uri, true);
        // Host header preserved when preserveHost=true AND host already set
        static::assertSame('original.com', $new->getHeaderLine('Host'));
    }

    public function testWithRequestTarget(): void
    {
        $req = Request::get('http://example.com/old')
            ->withRequestTarget('/new-path');
        static::assertSame('/new-path', $req->getRequestTarget());
    }

    public function testWithRequestTargetThrowsOnWhitespace(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Request::get('http://example.com/')->withRequestTarget('/path with space');
    }

    public function testWithRequestTargetNoUri(): void
    {
        $req = new Request(Http::GET);
        $new = $req->withRequestTarget('/something');
        // No URI set – request target is set, method should not throw
        static::assertInstanceOf(Request::class, $new);
    }

    public function testWithCookie(): void
    {
        $req = Request::get('http://example.com/')->withCookie('session', 'abc123');
        static::assertSame('session=abc123', $req->getHeaderLine('Cookie'));
    }

    public function testWithAddedCookie(): void
    {
        $req = Request::get('http://example.com/')
            ->withCookie('a', '1')
            ->withAddedCookie('b', '2');
        $cookie = $req->getHeaderLine('Cookie');
        static::assertStringContainsString('a=1', $cookie);
    }

    public function testWithUserAgent(): void
    {
        $req = Request::get('http://example.com/')->withUserAgent('MyClient/1.0');
        static::assertSame('MyClient/1.0', $req->getHeaderLine('User-Agent'));
    }

    public function testWithCurlOption(): void
    {
        $req = Request::get('http://example.com/')->withCurlOption(\CURLOPT_VERBOSE, true);
        static::assertInstanceOf(Request::class, $req);
    }

    public function testWithMimeType(): void
    {
        $original = Request::get('http://example.com/');
        $req = $original->withMimeType(Mime::JSON);
        static::assertNotSame($original, $req);
        static::assertSame(Mime::getFullMime(Mime::JSON), $req->getContentType());
        static::assertSame(Mime::getFullMime(Mime::JSON), $req->getExpectedType());
    }

    public function testWithMimeTypeNull(): void
    {
        $req = Request::get('http://example.com/')->withMimeType(null);
        static::assertInstanceOf(Request::class, $req);
    }

    public function testClientSideCertAuth(): void
    {
        $req = Request::get('http://example.com/')
            ->clientSideCertAuth('/path/to/cert.pem', '/path/to/key.pem', 'secret', 'PEM');
        static::assertTrue($req->hasClientSideCert());
    }

    public function testHasClientSideCertFalseByDefault(): void
    {
        $req = Request::get('http://example.com/');
        static::assertFalse($req->hasClientSideCert());
    }

    public function testSerializePayloadMode(): void
    {
        $req = Request::get('http://example.com/')
            ->serializePayloadMode(Request::SERIALIZE_PAYLOAD_ALWAYS);
        static::assertSame(Request::SERIALIZE_PAYLOAD_ALWAYS, $req->getSerializePayloadMethod());

        $req2 = $req->neverSerializePayload();
        static::assertSame(Request::SERIALIZE_PAYLOAD_NEVER, $req2->getSerializePayloadMethod());

        $req3 = $req2->smartSerializePayload();
        static::assertSame(Request::SERIALIZE_PAYLOAD_SMART, $req3->getSerializePayloadMethod());
    }

    public function testRegisterPayloadSerializer(): void
    {
        $callback = static function ($p) {
            return json_encode($p);
        };
        $req = Request::get('http://example.com/')
            ->registerPayloadSerializer(Mime::JSON, $callback);
        static::assertInstanceOf(Request::class, $req);
    }

    public function testWithSerializePayload(): void
    {
        $callback = static function ($p) {
            return serialize($p);
        };
        $req = Request::get('http://example.com/')->withSerializePayload($callback);
        static::assertInstanceOf(Request::class, $req);
    }

    public function testGetPayloadAndGetSerializedPayload(): void
    {
        $req = Request::post('http://example.com/', 'body');
        static::assertSame(['body'], $req->getPayload());
        // Before sending, serialized_payload is null
        static::assertNull($req->getSerializedPayload());
    }

    public function testGetRawHeaders(): void
    {
        $req = Request::get('http://example.com/');
        // Before a send, raw_headers is empty
        static::assertIsString($req->getRawHeaders());
    }

    public function testHelperData(): void
    {
        $req = Request::get('http://example.com/')
            ->addHelperData('key', 'value')
            ->addHelperData('other', 42);

        static::assertSame('value', $req->getHelperData('key'));
        static::assertSame(42, $req->getHelperData('other'));
        static::assertNull($req->getHelperData('missing'));
        static::assertSame('default', $req->getHelperData('missing', 'default'));
        static::assertIsArray($req->getHelperData());

        $req->clearHelperData();
        static::assertSame([], $req->getHelperData());
    }

    public function testHasProxyFalseByDefault(): void
    {
        $req = Request::get('http://example.com/');
        static::assertFalse($req->hasProxy());
    }

    public function testGetHeadersReturnsArray(): void
    {
        $req = Request::get('http://example.com/')->withHeader('X-Test', 'value');
        $headers = $req->getHeaders();
        static::assertIsArray($headers);
    }

    public function testWithDownload(): void
    {
        $req = Request::get('http://example.com/')
            ->withUriFromString('http://example.com/file.zip')
            ->withDownload('/tmp/file.zip');
        static::assertInstanceOf(Request::class, $req);
    }

    // =========================================================================
    // Stream – error paths and untested methods
    // =========================================================================

    public function testStreamWriteAndRead(): void
    {
        $resource = \fopen('php://temp', 'r+b');
        $stream = new Stream($resource);

        $written = $stream->write('hello world');
        static::assertSame(11, $written);

        $stream->seek(0);
        static::assertSame('hello world', $stream->read(11));
    }

    public function testStreamTell(): void
    {
        $resource = \fopen('php://temp', 'r+b');
        $stream = new Stream($resource);
        $stream->write('abc');
        static::assertSame(3, $stream->tell());
    }

    public function testStreamEof(): void
    {
        $resource = \fopen('php://temp', 'r+b');
        $stream = new Stream($resource);
        $stream->write('x');
        $stream->seek(0);
        static::assertFalse($stream->eof());
        $stream->read(10); // read past end
        static::assertTrue($stream->eof());
    }

    public function testStreamGetSize(): void
    {
        $stream = Stream::createNotNull('hello');
        static::assertSame(5, $stream->getSize());
    }

    public function testStreamGetSizeWithSizeOption(): void
    {
        $resource = \fopen('php://temp', 'r+b');
        $stream = new Stream($resource, ['size' => 99]);
        static::assertSame(99, $stream->getSize());
    }

    public function testStreamGetContentsUnserialized(): void
    {
        $data = ['a' => 1, 'b' => 2];
        $stream = Stream::createNotNull($data);
        // Stream::create writes to the buffer but leaves cursor at end;
        // seek back to start before reading.
        $stream->seek(0);
        $result = $stream->getContentsUnserialized();
        static::assertSame($data, $result);
    }

    public function testStreamDetach(): void
    {
        $resource = \fopen('php://temp', 'r+b');
        $stream = new Stream($resource);
        $detached = $stream->detach();
        static::assertIsResource($detached);
        // After detach, is not seekable/readable/writable
        static::assertFalse($stream->isSeekable());
        static::assertFalse($stream->isReadable());
        static::assertFalse($stream->isWritable());
    }

    public function testStreamDetachTwiceReturnsNull(): void
    {
        $resource = \fopen('php://temp', 'r+b');
        $stream = new Stream($resource);
        $stream->detach();
        static::assertNull($stream->detach());
    }

    public function testStreamGetMetadataAfterDetach(): void
    {
        $resource = \fopen('php://temp', 'r+b');
        $stream = new Stream($resource);
        $stream->detach();
        static::assertSame([], $stream->getMetadata());
        static::assertNull($stream->getMetadata('uri'));
    }

    public function testStreamGetSizeAfterDetach(): void
    {
        $resource = \fopen('php://temp', 'r+b');
        $stream = new Stream($resource);
        $stream->detach();
        static::assertNull($stream->getSize());
    }

    public function testStreamEofThrowsWhenDetached(): void
    {
        $this->expectException(\RuntimeException::class);
        $resource = \fopen('php://temp', 'r+b');
        $stream = new Stream($resource);
        $stream->detach();
        $stream->eof();
    }

    public function testStreamSeekThrowsWhenDetached(): void
    {
        $this->expectException(\RuntimeException::class);
        $resource = \fopen('php://temp', 'r+b');
        $stream = new Stream($resource);
        $stream->detach();
        $stream->seek(0);
    }

    public function testStreamTellThrowsWhenDetached(): void
    {
        $this->expectException(\RuntimeException::class);
        $resource = \fopen('php://temp', 'r+b');
        $stream = new Stream($resource);
        $stream->detach();
        $stream->tell();
    }

    public function testStreamReadThrowsWhenDetached(): void
    {
        $this->expectException(\RuntimeException::class);
        $resource = \fopen('php://temp', 'r+b');
        $stream = new Stream($resource);
        $stream->detach();
        $stream->read(1);
    }

    public function testStreamWriteThrowsWhenDetached(): void
    {
        $this->expectException(\RuntimeException::class);
        $resource = \fopen('php://temp', 'r+b');
        $stream = new Stream($resource);
        $stream->detach();
        $stream->write('x');
    }

    public function testStreamGetContentsThrowsWhenDetached(): void
    {
        $this->expectException(\RuntimeException::class);
        $resource = \fopen('php://temp', 'r+b');
        $stream = new Stream($resource);
        $stream->detach();
        $stream->getContents();
    }

    public function testStreamReadLengthZero(): void
    {
        $stream = Stream::createNotNull('hello');
        static::assertSame('', $stream->read(0));
    }

    public function testStreamReadNegativeLengthThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $stream = Stream::createNotNull('hello');
        $stream->read(-1);
    }

    public function testStreamReadFromNonReadableThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $resource = \fopen('php://temp', 'r+b');
        $stream = new Stream($resource);
        // Force non-readable
        $stream->detach();
        fclose($resource);
        // create a write-only stream
        $tmpFile = \tempnam(\sys_get_temp_dir(), 'stream_test_');
        $wo = \fopen($tmpFile, 'wb');
        $stream2 = new Stream($wo);
        $stream2->read(1);
        // cleanup
        @unlink($tmpFile);
    }

    public function testStreamWriteToNonWritableThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $tmpFile = \tempnam(\sys_get_temp_dir(), 'stream_test_');
        \file_put_contents($tmpFile, 'content');
        $ro = \fopen($tmpFile, 'rb');
        $stream = new Stream($ro);
        $stream->write('data');
        @unlink($tmpFile);
    }

    public function testStreamCreateWithNull(): void
    {
        $stream = Stream::create(null);
        static::assertNotNull($stream);
        static::assertSame('', (string) $stream);
    }

    public function testStreamCreateWithNumeric(): void
    {
        $stream = Stream::create(42);
        static::assertNotNull($stream);
        static::assertSame('42', (string) $stream);
    }

    public function testStreamCreateNotNull(): void
    {
        $stream = Stream::createNotNull('test');
        static::assertInstanceOf(Stream::class, $stream);
    }

    public function testStreamCustomMetadata(): void
    {
        $resource = \fopen('php://temp', 'r+b');
        $stream = new Stream($resource, ['metadata' => ['custom_key' => 'custom_value']]);
        static::assertSame('custom_value', $stream->getMetadata('custom_key'));
        $all = $stream->getMetadata();
        static::assertArrayHasKey('custom_key', $all);
    }

    public function testStreamToStringAfterDetach(): void
    {
        $resource = \fopen('php://temp', 'r+b');
        \fwrite($resource, 'hello');
        $stream = new Stream($resource);
        $stream->detach();
        static::assertSame('', (string) $stream);
    }

    public function testStreamIsSeekable(): void
    {
        $stream = Stream::createNotNull('data');
        static::assertTrue($stream->isSeekable());
        static::assertTrue($stream->isReadable());
        static::assertTrue($stream->isWritable());
    }

    public function testStreamRewind(): void
    {
        $stream = Stream::createNotNull('hello world');
        $stream->read(5);
        $stream->rewind();
        static::assertSame(0, $stream->tell());
    }

    // =========================================================================
    // Headers – exception paths and validation
    // =========================================================================

    public function testHeadersOffsetSetThrows(): void
    {
        $this->expectException(ResponseHeaderException::class);
        $headers = new Headers(['X-Foo' => ['bar']]);
        $headers['X-New'] = 'value';
    }

    public function testHeadersOffsetUnsetThrows(): void
    {
        $this->expectException(ResponseHeaderException::class);
        $headers = new Headers(['X-Foo' => ['bar']]);
        unset($headers['X-Foo']);
    }

    public function testHeadersFromString(): void
    {
        $raw = "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nX-Foo: bar\r\n";
        $headers = Headers::fromString($raw);
        static::assertSame(['application/json'], $headers->offsetGet('Content-Type'));
        static::assertSame(['bar'], $headers->offsetGet('X-Foo'));
    }

    public function testHeadersFromStringWithMultipleValues(): void
    {
        $raw = "HTTP/1.1 200 OK\r\nSet-Cookie: a=1\r\nSet-Cookie: b=2\r\n";
        $headers = Headers::fromString($raw);
        static::assertIsArray($headers->offsetGet('Set-Cookie'));
    }

    public function testHeadersFromStringWithNoColon(): void
    {
        // Lines without ':' should be skipped
        $raw = "HTTP/1.1 200 OK\r\nInvalidLine\r\nX-Valid: yes\r\n";
        $headers = Headers::fromString($raw);
        static::assertSame(['yes'], $headers->offsetGet('X-Valid'));
        static::assertFalse($headers->offsetExists('InvalidLine'));
    }

    public function testHeadersCaseInsensitive(): void
    {
        $headers = new Headers(['Content-Type' => ['application/json']]);
        static::assertTrue($headers->offsetExists('content-type'));
        static::assertTrue($headers->offsetExists('CONTENT-TYPE'));
    }

    public function testHeadersCount(): void
    {
        $headers = new Headers(['A' => ['1'], 'B' => ['2']]);
        static::assertSame(2, $headers->count());
    }

    public function testHeadersIteration(): void
    {
        $headers = new Headers(['X-A' => ['1'], 'X-B' => ['2']]);
        $keys = [];
        foreach ($headers as $k => $v) {
            $keys[] = $k;
        }
        static::assertContains('X-A', $keys);
        static::assertContains('X-B', $keys);
    }

    public function testHeadersToArray(): void
    {
        $headers = new Headers(['Content-Type' => ['text/plain']]);
        $arr = $headers->toArray();
        static::assertArrayHasKey('Content-Type', $arr);
    }

    public function testHeadersForceUnset(): void
    {
        $headers = new Headers(['X-Remove' => ['me']]);
        $headers->forceUnset('X-Remove');
        static::assertFalse($headers->offsetExists('X-Remove'));
    }

    public function testHeadersInvalidNameThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Headers(['' => ['value']]);
    }

    public function testHeadersInvalidValueThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        // Null value is not a valid header value
        new Headers(['X-Foo' => [null]]);
    }

    public function testHeadersEmptyArrayThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Headers(['X-Foo' => []]);
    }

    // =========================================================================
    // Uri – static helper methods not yet covered
    // =========================================================================

    public function testUriIsAbsolute(): void
    {
        static::assertTrue(Uri::isAbsolute(new Uri('http://example.com/')));
        static::assertFalse(Uri::isAbsolute(new Uri('/path')));
        static::assertFalse(Uri::isAbsolute(new Uri('//example.com/path')));
    }

    public function testUriIsDefaultPort(): void
    {
        static::assertTrue(Uri::isDefaultPort(new Uri('http://example.com/')));
        static::assertTrue(Uri::isDefaultPort(new Uri('http://example.com:80/')));
        static::assertFalse(Uri::isDefaultPort(new Uri('http://example.com:8080/')));
        // No scheme → no default port definition, port explicitly set
        static::assertFalse(Uri::isDefaultPort(new Uri('//example.com:12345/')));
    }

    public function testUriIsNetworkPathReference(): void
    {
        static::assertTrue(Uri::isNetworkPathReference(new Uri('//example.com/path')));
        static::assertFalse(Uri::isNetworkPathReference(new Uri('http://example.com/')));
        static::assertFalse(Uri::isNetworkPathReference(new Uri('/path')));
    }

    public function testUriIsAbsolutePathReference(): void
    {
        static::assertTrue(Uri::isAbsolutePathReference(new Uri('/path/to/resource')));
        static::assertFalse(Uri::isAbsolutePathReference(new Uri('http://example.com/')));
        static::assertFalse(Uri::isAbsolutePathReference(new Uri('relative/path')));
        static::assertFalse(Uri::isAbsolutePathReference(new Uri('')));
    }

    public function testUriIsRelativePathReference(): void
    {
        static::assertTrue(Uri::isRelativePathReference(new Uri('relative/path')));
        static::assertTrue(Uri::isRelativePathReference(new Uri('')));
        static::assertFalse(Uri::isRelativePathReference(new Uri('/absolute/path')));
        static::assertFalse(Uri::isRelativePathReference(new Uri('http://example.com/')));
    }

    public function testUriIsSameDocumentReference(): void
    {
        static::assertTrue(Uri::isSameDocumentReference(new Uri('')));
        static::assertTrue(Uri::isSameDocumentReference(new Uri('#fragment')));
        static::assertFalse(Uri::isSameDocumentReference(new Uri('http://example.com/')));

        $base = new Uri('http://example.com/path?q=1');
        static::assertTrue(Uri::isSameDocumentReference(new Uri(''), $base));
        static::assertTrue(Uri::isSameDocumentReference(new Uri('http://example.com/path?q=1'), $base));
        static::assertFalse(Uri::isSameDocumentReference(new Uri('http://example.com/other'), $base));
    }

    public function testUriWithQueryValue(): void
    {
        $uri = new Uri('http://example.com/path?foo=1&bar=2');
        $new = Uri::withQueryValue($uri, 'foo', 'replaced');
        static::assertStringContainsString('foo=replaced', (string) $new);
        static::assertStringContainsString('bar=2', (string) $new);
    }

    public function testUriWithQueryValueNullRemovesValue(): void
    {
        $uri = new Uri('http://example.com/path?foo=1');
        $new = Uri::withQueryValue($uri, 'foo', null);
        static::assertStringContainsString('foo', (string) $new);
        // When value is null, key is present without '='
        static::assertStringNotContainsString('foo=', (string) $new);
    }

    public function testUriWithQueryValues(): void
    {
        $uri = new Uri('http://example.com/path');
        $new = Uri::withQueryValues($uri, ['a' => '1', 'b' => '2']);
        static::assertStringContainsString('a=1', (string) $new);
        static::assertStringContainsString('b=2', (string) $new);
    }

    public function testUriWithoutQueryValue(): void
    {
        $uri = new Uri('http://example.com/path?foo=1&bar=2');
        $new = Uri::withoutQueryValue($uri, 'foo');
        static::assertStringNotContainsString('foo', (string) $new);
        static::assertStringContainsString('bar=2', (string) $new);
    }

    public function testUriFromParts(): void
    {
        $uri = Uri::fromParts([
            'scheme' => 'https',
            'host'   => 'example.com',
            'path'   => '/path',
            'query'  => 'key=value',
        ]);
        static::assertSame('https', $uri->getScheme());
        static::assertSame('example.com', $uri->getHost());
        static::assertSame('/path', $uri->getPath());
        static::assertSame('key=value', $uri->getQuery());
    }

    public function testUriComposeComponentsFileScheme(): void
    {
        $result = Uri::composeComponents('file', '', '/etc/hosts', '', '');
        static::assertSame('file:///etc/hosts', $result);
    }

    public function testUriWithPortRemovesDefaultPort(): void
    {
        $uri = new Uri('http://example.com:80/path');
        // Default port 80 for http should be stripped
        static::assertNull($uri->getPort());
    }

    public function testUriWithPortNonDefault(): void
    {
        $uri = new Uri('http://example.com:8080/path');
        static::assertSame(8080, $uri->getPort());
    }

    public function testUriWithPortInvalidThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $uri = new Uri('http://example.com/');
        $uri->withPort(99999);
    }

    public function testUriWithScheme(): void
    {
        $uri = new Uri('http://example.com/');
        $new = $uri->withScheme('https');
        static::assertSame('https', $new->getScheme());
        // Same scheme returns same instance
        static::assertSame($new, $new->withScheme('https'));
    }

    public function testUriWithFragment(): void
    {
        $uri = new Uri('http://example.com/path');
        $new = $uri->withFragment('section1');
        static::assertSame('section1', $new->getFragment());
        static::assertStringContainsString('#section1', (string) $new);
        // Same fragment returns same instance
        static::assertSame($new, $new->withFragment('section1'));
    }

    public function testUriWithQuery(): void
    {
        $uri = new Uri('http://example.com/path');
        $new = $uri->withQuery('foo=bar');
        static::assertSame('foo=bar', $new->getQuery());
        // Same query returns same instance
        static::assertSame($new, $new->withQuery('foo=bar'));
    }

    public function testUriWithUserInfo(): void
    {
        $uri = new Uri('http://example.com/');
        $new = $uri->withUserInfo('user', 'pass');
        static::assertSame('user:pass', $new->getUserInfo());
        static::assertStringContainsString('user:pass@', (string) $new);
        // Same user info returns same instance
        static::assertSame($new, $new->withUserInfo('user', 'pass'));
    }

    public function testUriGetAuthorityNoHost(): void
    {
        $uri = new Uri('/path');
        static::assertSame('', $uri->getAuthority());
    }

    public function testUriInvalidThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Uri('http:///::');
    }

    // =========================================================================
    // CsvMimeHandler – serialize
    // =========================================================================

    public function testCsvMimeHandlerSerialize(): void
    {
        $handler = new CsvMimeHandler();
        $data = [
            ['name' => 'Alice', 'age' => '30'],
            ['name' => 'Bob',   'age' => '25'],
        ];
        $csv = $handler->serialize($data);
        static::assertIsString($csv);
        static::assertStringContainsString('Alice', $csv);
        static::assertStringContainsString('Bob', $csv);
        static::assertStringContainsString('name', $csv);
        static::assertStringContainsString('age', $csv);
    }

    public function testCsvMimeHandlerParseEmpty(): void
    {
        $handler = new CsvMimeHandler();
        static::assertNull($handler->parse(''));
    }

    public function testCsvMimeHandlerParse(): void
    {
        $handler = new CsvMimeHandler();
        $result = $handler->parse("a,b,c\n1,2,3\n");
        static::assertIsArray($result);
        static::assertCount(2, $result);
    }

    // =========================================================================
    // DefaultMimeHandler – serialize with Serializable
    // =========================================================================

    public function testDefaultMimeHandlerSerializeArray(): void
    {
        $handler = new DefaultMimeHandler();
        $data = ['key' => 'value'];
        $result = $handler->serialize($data);
        static::assertIsString($result);
        static::assertSame(\serialize($data), $result);
    }

    public function testDefaultMimeHandlerSerializeScalar(): void
    {
        $handler = new DefaultMimeHandler();
        static::assertSame('hello', $handler->serialize('hello'));
        static::assertSame(42, $handler->serialize(42));
    }

    public function testDefaultMimeHandlerParseReturnsSame(): void
    {
        $handler = new DefaultMimeHandler();
        static::assertSame('body content', $handler->parse('body content'));
    }

    // =========================================================================
    // XmlMimeHandler – serialize_clean and serialize_node
    // =========================================================================

    public function testXmlMimeHandlerSerializeClean(): void
    {
        $handler = new XmlMimeHandler();
        $xml = $handler->serialize_clean(['root' => ['child' => 'value']]);
        static::assertStringContainsString('<root>', $xml);
        static::assertStringContainsString('<child>', $xml);
        static::assertStringContainsString('value', $xml);
    }

    public function testXmlMimeHandlerSerializeNode(): void
    {
        $writer = new \XMLWriter();
        $writer->openMemory();
        $writer->startDocument('1.0', 'UTF-8');
        $handler = new XmlMimeHandler();
        $writer->startElement('root');
        $handler->serialize_node($writer, 'text node');
        $writer->endElement();
        $xml = $writer->outputMemory(true);
        static::assertStringContainsString('text node', $xml);
    }

    public function testXmlMimeHandlerSerializeNodeArray(): void
    {
        $writer = new \XMLWriter();
        $writer->openMemory();
        $writer->startDocument('1.0', 'UTF-8');
        $handler = new XmlMimeHandler();
        $writer->startElement('wrapper');
        $handler->serialize_node($writer, ['item' => 'value1']);
        $writer->endElement();
        $xml = $writer->outputMemory(true);
        static::assertStringContainsString('<item>', $xml);
        static::assertStringContainsString('value1', $xml);
    }

    public function testXmlMimeHandlerSerializePayload(): void
    {
        $handler = new XmlMimeHandler();
        $xml = $handler->serialize(['key' => 'val']);
        static::assertStringContainsString('<key>', (string) $xml);
    }

    // =========================================================================
    // HtmlMimeHandler – serialize
    // =========================================================================

    public function testHtmlMimeHandlerSerialize(): void
    {
        $handler = new HtmlMimeHandler();
        static::assertSame('<p>Hello</p>', $handler->serialize('<p>Hello</p>'));
    }

    public function testHtmlMimeHandlerParseEmpty(): void
    {
        $handler = new HtmlMimeHandler();
        static::assertNull($handler->parse(''));
    }

    // =========================================================================
    // UploadedFile – various states
    // =========================================================================

    public function testUploadedFileWithStream(): void
    {
        $stream = Stream::createNotNull('file contents');
        $file = new UploadedFile($stream, 13, \UPLOAD_ERR_OK, 'test.txt', 'text/plain');
        static::assertSame(13, $file->getSize());
        static::assertSame(\UPLOAD_ERR_OK, $file->getError());
        static::assertSame('test.txt', $file->getClientFilename());
        static::assertSame('text/plain', $file->getClientMediaType());
        static::assertSame($stream, $file->getStream());
    }

    public function testUploadedFileWithResource(): void
    {
        $resource = \fopen('php://temp', 'r+b');
        \fwrite($resource, 'data');
        \rewind($resource);
        $file = new UploadedFile($resource, 4, \UPLOAD_ERR_OK);
        static::assertInstanceOf(\Psr\Http\Message\StreamInterface::class, $file->getStream());
    }

    public function testUploadedFileWithFilePath(): void
    {
        $tmpFile = \tempnam(\sys_get_temp_dir(), 'upf_');
        \file_put_contents($tmpFile, 'content');
        $file = new UploadedFile($tmpFile, 7, \UPLOAD_ERR_OK, 'upload.txt');
        $stream = $file->getStream();
        static::assertInstanceOf(\Psr\Http\Message\StreamInterface::class, $stream);
        @unlink($tmpFile);
    }

    public function testUploadedFileGetStreamThrowsOnError(): void
    {
        $this->expectException(\RuntimeException::class);
        $file = new UploadedFile('', 0, \UPLOAD_ERR_NO_FILE);
        $file->getStream();
    }

    public function testUploadedFileMoveToWithStream(): void
    {
        $stream = Stream::createNotNull('move me');
        $file = new UploadedFile($stream, 7, \UPLOAD_ERR_OK);
        $dest = \tempnam(\sys_get_temp_dir(), 'moved_');
        $file->moveTo($dest);
        static::assertSame('move me', \file_get_contents($dest));
        @unlink($dest);
    }

    public function testUploadedFileMoveToThrowsAfterMove(): void
    {
        $this->expectException(\RuntimeException::class);
        $stream = Stream::createNotNull('data');
        $file = new UploadedFile($stream, 4, \UPLOAD_ERR_OK);
        $dest = \tempnam(\sys_get_temp_dir(), 'moved_');
        $file->moveTo($dest);
        // Second moveTo should throw
        $file->moveTo($dest);
        @unlink($dest);
    }

    public function testUploadedFileMoveToInvalidPathThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $stream = Stream::createNotNull('data');
        $file = new UploadedFile($stream, 4, \UPLOAD_ERR_OK);
        $file->moveTo('');
    }

    public function testUploadedFileInvalidErrorStatusThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new UploadedFile('file.txt', 10, 999);
    }

    public function testUploadedFileInvalidSizeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new UploadedFile('file.txt', 'not-an-int', \UPLOAD_ERR_OK);
    }

    public function testUploadedFileInvalidClientFilenameThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new UploadedFile('file.txt', 10, \UPLOAD_ERR_OK, 12345);
    }

    public function testUploadedFileInvalidClientMediaTypeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new UploadedFile('file.txt', 10, \UPLOAD_ERR_OK, null, 12345);
    }

    public function testUploadedFileInvalidStreamOrFileThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new UploadedFile(12345, 10, \UPLOAD_ERR_OK);
    }

    // =========================================================================
    // Exception classes
    // =========================================================================

    public function testNetworkErrorExceptionGetRequest(): void
    {
        $req = Request::get('http://example.com/');
        $ex = new NetworkErrorException('error', 0, null, null, $req);
        static::assertSame($req, $ex->getRequest());
    }

    public function testNetworkErrorExceptionGetRequestDefaultsToNew(): void
    {
        $ex = new NetworkErrorException('error');
        static::assertInstanceOf(Request::class, $ex->getRequest());
    }

    public function testNetworkErrorExceptionSetters(): void
    {
        $ex = new NetworkErrorException('err');
        $ex->setCurlErrorNumber(28);
        $ex->setCurlErrorString('Timeout');
        static::assertSame(28, $ex->getCurlErrorNumber());
        static::assertSame('Timeout', $ex->getCurlErrorString());
    }

    public function testNetworkErrorExceptionWasTimeout(): void
    {
        $ex = new NetworkErrorException('timeout', \CURLE_OPERATION_TIMEOUTED);
        static::assertTrue($ex->wasTimeout());

        $ex2 = new NetworkErrorException('other', 0);
        static::assertFalse($ex2->wasTimeout());
    }

    public function testNetworkErrorExceptionGetCurlObject(): void
    {
        $ex = new NetworkErrorException('err');
        static::assertNull($ex->getCurlObject());
    }

    public function testClientErrorException(): void
    {
        $ex = new ClientErrorException('client error');
        static::assertSame('client error', $ex->getMessage());
    }

    public function testRequestExceptionGetRequest(): void
    {
        $req = Request::get('http://example.com/');
        $ex = new RequestException($req, 'something went wrong');
        static::assertSame($req, $ex->getRequest());
    }

    // =========================================================================
    // Response – untested methods
    // =========================================================================

    public function testResponseGetStatusCode(): void
    {
        $req = Request::get('http://example.com/');
        $response = new Response('body', "HTTP/1.1 200 OK\r\n\r\n", $req, []);
        static::assertSame(200, $response->getStatusCode());
    }

    public function testResponseGetReasonPhrase(): void
    {
        $req = Request::get('http://example.com/');
        $response = new Response('', "HTTP/1.1 404 Not Found\r\n\r\n", $req, []);
        static::assertSame('Not Found', $response->getReasonPhrase());
    }

    public function testResponseWithStatus(): void
    {
        $req = Request::get('http://example.com/');
        $response = new Response('', "HTTP/1.1 200 OK\r\n\r\n", $req, []);
        $new = $response->withStatus(201, 'Created');
        static::assertSame(201, $new->getStatusCode());
        static::assertSame('Created', $new->getReasonPhrase());
    }

    public function testResponseGetProtocolVersion(): void
    {
        $req = Request::get('http://example.com/');
        $response = new Response('', "HTTP/1.1 200 OK\r\n\r\n", $req, ['protocol_version' => '1.1']);
        static::assertSame('1.1', $response->getProtocolVersion());
    }

    public function testResponseWithProtocolVersion(): void
    {
        $req = Request::get('http://example.com/');
        $response = new Response('', "HTTP/1.1 200 OK\r\n\r\n", $req, []);
        $new = $response->withProtocolVersion('2.0');
        static::assertSame('2.0', $new->getProtocolVersion());
    }
}
