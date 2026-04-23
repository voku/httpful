<?php

declare(strict_types=1);

namespace Httpful\tests;

use Httpful\Exception\ClientErrorException;
use Httpful\Exception\NetworkErrorException;
use Httpful\Exception\ResponseException;
use Httpful\Exception\XmlParseException;
use Httpful\Factory;
use Httpful\Handlers\CsvMimeHandler;
use Httpful\Handlers\FormMimeHandler;
use Httpful\Handlers\HtmlMimeHandler;
use Httpful\Handlers\XmlMimeHandler;
use Httpful\Http;
use Httpful\Mime;
use Httpful\Request;
use Httpful\Setup;
use Httpful\Uri;
use Httpful\UriResolver;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

/**
 * @internal
 */
final class CoverageBoostTest extends TestCase
{
    // -----------------------------------------------------------------------
    // UriResolver
    // -----------------------------------------------------------------------

    public function testUnparseUrlFull(): void
    {
        $parsed = [
            'scheme'   => 'https',
            'user'     => 'alice',
            'pass'     => 's3cr3t',
            'host'     => 'example.com',
            'port'     => 8080,
            'path'     => '/foo/bar',
            'query'    => 'baz=1',
            'fragment' => 'anchor',
        ];
        $url = UriResolver::unparseUrl($parsed);
        static::assertSame('https://alice:s3cr3t@example.com:8080/foo/bar?baz=1#anchor', $url);
    }

    public function testUnparseUrlMinimal(): void
    {
        $url = UriResolver::unparseUrl(['host' => 'example.com', 'path' => '/']);
        static::assertSame('example.com/', $url);
    }

    public function testUnparseUrlUserWithoutPassword(): void
    {
        $parsed = ['scheme' => 'http', 'user' => 'alice', 'host' => 'example.com', 'path' => '/'];
        $url = UriResolver::unparseUrl($parsed);
        // user present without pass → user@ format
        static::assertSame('http://alice@example.com/', $url);
    }

    public function testUnparseUrlSchemeOnly(): void
    {
        $url = UriResolver::unparseUrl(['scheme' => 'ftp', 'host' => 'files.example.com']);
        static::assertSame('ftp://files.example.com', $url);
    }

    public function testRemoveDotSegmentsEmpty(): void
    {
        static::assertSame('', UriResolver::removeDotSegments(''));
    }

    public function testRemoveDotSegmentsSlash(): void
    {
        static::assertSame('/', UriResolver::removeDotSegments('/'));
    }

    public function testRemoveDotSegmentsSingleDot(): void
    {
        static::assertSame('/a/b/', UriResolver::removeDotSegments('/a/./b/.'));
    }

    public function testRemoveDotSegmentsDoubleDot(): void
    {
        static::assertSame('/a/', UriResolver::removeDotSegments('/a/b/../'));
    }

    public function testRemoveDotSegmentsLeadingDoubleDot(): void
    {
        // Leading /.. should result in /
        static::assertSame('/', UriResolver::removeDotSegments('/..'));
    }

    public function testRemoveDotSegmentsTrailingDoubleDot(): void
    {
        // Trailing .. should add trailing slash
        static::assertSame('/a/', UriResolver::removeDotSegments('/a/b/..'));
    }

    public function testResolveEmptyReference(): void
    {
        $base = new Uri('http://example.com/a/b/c');
        $rel  = new Uri('');
        $result = UriResolver::resolve($base, $rel);
        static::assertSame('http://example.com/a/b/c', (string) $result);
    }

    public function testResolveAbsoluteReference(): void
    {
        $base   = new Uri('http://example.com/a/b');
        $rel    = new Uri('http://other.com/x');
        $result = UriResolver::resolve($base, $rel);
        static::assertSame('http://other.com/x', (string) $result);
    }

    public function testResolveRelativePath(): void
    {
        $base   = new Uri('http://example.com/a/b/c');
        $rel    = new Uri('../d');
        $result = UriResolver::resolve($base, $rel);
        static::assertSame('http://example.com/a/d', (string) $result);
    }

    public function testResolveAbsolutePath(): void
    {
        $base   = new Uri('http://example.com/a/b/c');
        $rel    = new Uri('/x/y');
        $result = UriResolver::resolve($base, $rel);
        static::assertSame('http://example.com/x/y', (string) $result);
    }

    public function testResolveWithAuthority(): void
    {
        $base   = new Uri('http://example.com/a/b');
        $rel    = new Uri('//other.com/path');
        $result = UriResolver::resolve($base, $rel);
        static::assertSame('http://other.com/path', (string) $result);
    }

    public function testResolveEmptyPath(): void
    {
        $base   = new Uri('http://example.com/a/b?q=1');
        $rel    = new Uri('?other=2');
        $result = UriResolver::resolve($base, $rel);
        static::assertSame('http://example.com/a/b?other=2', (string) $result);
    }

    public function testResolveEmptyPathPreservesBaseQuery(): void
    {
        $base   = new Uri('http://example.com/a/b?q=1');
        $rel    = new Uri('');
        $result = UriResolver::resolve($base, $rel);
        static::assertSame('http://example.com/a/b?q=1', (string) $result);
    }

    public function testRelativize(): void
    {
        $base   = new Uri('http://example.com/a/b/');
        $target = new Uri('http://example.com/a/b/c');
        $result = UriResolver::relativize($base, $target);
        static::assertSame('c', (string) $result);
    }

    public function testRelativizeDifferentScheme(): void
    {
        $base   = new Uri('http://example.com/a/b/');
        $target = new Uri('https://example.com/a/b/c');
        $result = UriResolver::relativize($base, $target);
        // Different scheme → return target as-is
        static::assertSame('https://example.com/a/b/c', (string) $result);
    }

    public function testRelativizeDifferentHost(): void
    {
        $base   = new Uri('http://example.com/a/b/');
        $target = new Uri('http://other.com/a/b/c');
        $result = UriResolver::relativize($base, $target);
        // Different authority → return without scheme
        static::assertSame('//other.com/a/b/c', (string) $result);
    }

    public function testRelativizeSamePath(): void
    {
        $base   = new Uri('http://example.com/a/b');
        $target = new Uri('http://example.com/a/b');
        $result = UriResolver::relativize($base, $target);
        // Same path and no query → empty (fragment only)
        static::assertSame('', (string) $result);
    }

    public function testRelativizeWithQuery(): void
    {
        $base   = new Uri('http://example.com/a/b/?q=1');
        $target = new Uri('http://example.com/a/b/?q=2');
        $result = UriResolver::relativize($base, $target);
        static::assertSame('?q=2', (string) $result);
    }

    // -----------------------------------------------------------------------
    // XmlMimeHandler
    // -----------------------------------------------------------------------

    public function testXmlParseEmpty(): void
    {
        $handler = new XmlMimeHandler();
        static::assertNull($handler->parse(''));
    }

    public function testXmlParseValid(): void
    {
        $handler = new XmlMimeHandler();
        $result  = $handler->parse('<root><item>hello</item></root>');
        static::assertInstanceOf(\SimpleXMLElement::class, $result);
        static::assertSame('hello', (string) $result->item);
    }

    public function testXmlParseInvalidThrows(): void
    {
        $this->expectException(XmlParseException::class);
        // Use LIBXML_NOERROR|LIBXML_NOWARNING so simplexml_load_string does not emit
        // PHP warnings (which PHPUnit converts to exceptions) before we can catch the
        // return value and throw XmlParseException ourselves.
        $handler = new XmlMimeHandler(['libxml_opts' => \LIBXML_NOERROR | \LIBXML_NOWARNING]);
        $handler->parse('not valid xml <<<');
    }

    public function testXmlSerialize(): void
    {
        $handler = new XmlMimeHandler();
        $result  = $handler->serialize(['root' => ['child' => 'value']]);
        static::assertIsString($result);
        static::assertStringContainsString('root', $result);
        static::assertStringContainsString('child', $result);
        static::assertStringContainsString('value', $result);
    }

    public function testXmlSerializeClean(): void
    {
        $handler = new XmlMimeHandler();
        $result  = $handler->serialize_clean(['root' => ['child' => 'value']]);
        static::assertIsString($result);
        static::assertStringContainsString('root', $result);
        static::assertStringContainsString('value', $result);
    }

    public function testXmlSerializeNode(): void
    {
        $handler = new XmlMimeHandler();
        $xml     = new \XMLWriter();
        $xml->openMemory();
        $xml->startElement('root');
        $handler->serialize_node($xml, 'leaf text');
        $xml->endElement();
        $output = $xml->outputMemory(true);
        static::assertStringContainsString('leaf text', $output);
    }

    public function testXmlSerializeNodeArray(): void
    {
        $handler = new XmlMimeHandler();
        $xml     = new \XMLWriter();
        $xml->openMemory();
        $handler->serialize_node($xml, ['key' => 'val']);
        $output = $xml->outputMemory(true);
        static::assertStringContainsString('<key>val</key>', $output);
    }

    // -----------------------------------------------------------------------
    // CsvMimeHandler
    // -----------------------------------------------------------------------

    public function testCsvParseEmpty(): void
    {
        $handler = new CsvMimeHandler();
        static::assertNull($handler->parse(''));
    }

    public function testCsvParseValid(): void
    {
        $handler = new CsvMimeHandler();
        $result  = $handler->parse("Name,Age\nAlice,30\nBob,25");
        static::assertIsArray($result);
        static::assertSame('Name', $result[0][0]);
        static::assertSame('Alice', $result[1][0]);
        static::assertSame('25', $result[2][1]);
    }

    public function testCsvSerialize(): void
    {
        $handler = new CsvMimeHandler();
        $data    = [
            ['name' => 'Alice', 'age' => '30'],
            ['name' => 'Bob',   'age' => '25'],
        ];
        $result = $handler->serialize($data);
        static::assertIsString($result);
        static::assertStringContainsString('name', $result);
        static::assertStringContainsString('Alice', $result);
        static::assertStringContainsString('Bob', $result);
    }

    // -----------------------------------------------------------------------
    // ClientErrorException
    // -----------------------------------------------------------------------

    public function testClientErrorExceptionBasic(): void
    {
        $e = new ClientErrorException('something failed', 42);
        static::assertSame('something failed', $e->getMessage());
        static::assertSame(42, $e->getCode());
        static::assertNull($e->getCurlObject());
    }

    public function testClientErrorExceptionCurlErrorSetters(): void
    {
        $e = new ClientErrorException('err');
        $e->setCurlErrorNumber(7);
        $e->setCurlErrorString('connection refused');
        static::assertSame(7, $e->getCurlErrorNumber());
        static::assertSame('connection refused', $e->getCurlErrorString());
    }

    public function testClientErrorExceptionWasTimeout(): void
    {
        // CURLE_OPERATION_TIMEOUTED = 28
        $e = new ClientErrorException('timeout', \CURLE_OPERATION_TIMEOUTED);
        static::assertTrue($e->wasTimeout());
    }

    public function testClientErrorExceptionWasNotTimeout(): void
    {
        $e = new ClientErrorException('not a timeout', 7);
        static::assertFalse($e->wasTimeout());
    }

    // -----------------------------------------------------------------------
    // Factory
    // -----------------------------------------------------------------------

    public function testFactoryCreateServerRequest(): void
    {
        $factory = new Factory();
        $req     = $factory->createServerRequest('POST', 'http://example.com/api', [], Mime::JSON, '{"key":"val"}');
        static::assertSame('POST', $req->getMethod());
        static::assertSame('example.com', $req->getUri()->getHost());
    }

    public function testFactoryCreateStreamFromResource(): void
    {
        $factory  = new Factory();
        $resource = \fopen('php://memory', 'r+b');
        \fwrite($resource, 'hello');
        \rewind($resource);
        $stream = $factory->createStreamFromResource($resource);
        static::assertInstanceOf(StreamInterface::class, $stream);
        static::assertSame('hello', (string) $stream);
    }

    public function testFactoryCreateStreamFromFile(): void
    {
        $factory = new Factory();
        $tmpFile = \tempnam(\sys_get_temp_dir(), 'httpful_test_');
        try {
            \file_put_contents($tmpFile, 'file content');
            $stream = $factory->createStreamFromFile($tmpFile, 'rb');
            static::assertSame('file content', (string) $stream);
        } finally {
            \unlink($tmpFile);
        }
    }

    public function testFactoryCreateStreamFromFileInvalidMode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $factory = new Factory();
        $factory->createStreamFromFile('/tmp/nonexistent_file_xyz.txt', 'z');
    }

    public function testFactoryCreateStreamFromFileNotFound(): void
    {
        $this->expectException(\RuntimeException::class);
        $factory = new Factory();
        $factory->createStreamFromFile('/tmp/this_file_does_not_exist_xyz123.txt', 'rb');
    }

    public function testFactoryCreateUploadedFile(): void
    {
        $factory = new Factory();
        $stream  = $factory->createStream('upload content');
        $uf      = $factory->createUploadedFile($stream, null, \UPLOAD_ERR_OK, 'test.txt', 'text/plain');
        static::assertInstanceOf(\Psr\Http\Message\UploadedFileInterface::class, $uf);
        static::assertSame('test.txt', $uf->getClientFilename());
    }

    public function testFactoryCreateUri(): void
    {
        $factory = new Factory();
        $uri     = $factory->createUri('https://example.com/path?q=1#frag');
        static::assertInstanceOf(\Psr\Http\Message\UriInterface::class, $uri);
        static::assertSame('https', $uri->getScheme());
        static::assertSame('example.com', $uri->getHost());
    }

    // -----------------------------------------------------------------------
    // FormMimeHandler
    // -----------------------------------------------------------------------

    public function testFormParseQueryString(): void
    {
        $handler = new FormMimeHandler();
        $result  = $handler->parse('foo=bar&baz=qux');
        static::assertIsArray($result);
        static::assertSame('bar', $result['foo']);
        static::assertSame('qux', $result['baz']);
    }

    public function testFormParseJsonBody(): void
    {
        $handler = new FormMimeHandler();
        $result  = $handler->parse('{"key":"value"}');
        static::assertIsArray($result);
        static::assertSame('value', $result['key']);
    }

    public function testFormSerialize(): void
    {
        $handler = new FormMimeHandler();
        $result  = $handler->serialize(['foo' => 'bar', 'num' => '42']);
        static::assertSame('foo=bar&num=42', $result);
    }

    // -----------------------------------------------------------------------
    // HtmlMimeHandler
    // -----------------------------------------------------------------------

    public function testHtmlParseEmpty(): void
    {
        $handler = new HtmlMimeHandler();
        static::assertNull($handler->parse(''));
    }

    public function testHtmlParseValid(): void
    {
        $handler = new HtmlMimeHandler();
        $result  = $handler->parse('<html><body><p>Hello</p></body></html>');
        static::assertInstanceOf(\voku\helper\HtmlDomParser::class, $result);
    }

    public function testHtmlSerialize(): void
    {
        $handler = new HtmlMimeHandler();
        static::assertSame('anything', $handler->serialize('anything'));
    }

    // -----------------------------------------------------------------------
    // Http
    // -----------------------------------------------------------------------

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
        static::assertContains(Http::TRACE, $methods);
    }

    public function testHttpIdempotentMethods(): void
    {
        $methods = Http::idempotentMethods();
        static::assertContains(Http::GET, $methods);
        static::assertNotContains(Http::POST, $methods);
    }

    public function testHttpSafeMethods(): void
    {
        $methods = Http::safeMethods();
        static::assertContains(Http::GET, $methods);
        static::assertContains(Http::HEAD, $methods);
        static::assertNotContains(Http::POST, $methods);
        static::assertNotContains(Http::PUT, $methods);
    }

    public function testHttpIsIdempotent(): void
    {
        static::assertTrue(Http::isIdempotent(Http::GET));
        static::assertTrue(Http::isIdempotent(Http::PUT));
        static::assertFalse(Http::isIdempotent(Http::POST));
    }

    public function testHttpIsNotIdempotent(): void
    {
        static::assertFalse(Http::isNotIdempotent(Http::GET));
        static::assertTrue(Http::isNotIdempotent(Http::POST));
    }

    public function testHttpIsSafeMethod(): void
    {
        static::assertTrue(Http::isSafeMethod(Http::GET));
        static::assertTrue(Http::isSafeMethod(Http::HEAD));
        static::assertFalse(Http::isSafeMethod(Http::POST));
        static::assertFalse(Http::isSafeMethod(Http::DELETE));
    }

    public function testHttpIsUnsafeMethod(): void
    {
        static::assertFalse(Http::isUnsafeMethod(Http::GET));
        static::assertTrue(Http::isUnsafeMethod(Http::POST));
        static::assertTrue(Http::isUnsafeMethod(Http::PUT));
    }

    public function testHttpReasonKnownCode(): void
    {
        static::assertSame('OK', Http::reason(200));
        static::assertSame('Not Found', Http::reason(404));
        static::assertSame('Internal Server Error', Http::reason(500));
    }

    public function testHttpReasonUnknownCodeThrows(): void
    {
        $this->expectException(ResponseException::class);
        Http::reason(999);
    }

    public function testHttpResponseCodeExists(): void
    {
        static::assertTrue(Http::responseCodeExists(200));
        static::assertTrue(Http::responseCodeExists(404));
        static::assertFalse(Http::responseCodeExists(999));
    }

    public function testHttpStreamWithResource(): void
    {
        $resource = \fopen('php://memory', 'r+b');
        $stream   = Http::stream($resource);
        static::assertInstanceOf(StreamInterface::class, $stream);
    }

    public function testHttpStreamWithNull(): void
    {
        $stream = Http::stream(null);
        static::assertInstanceOf(StreamInterface::class, $stream);
        static::assertSame('', (string) $stream);
    }

    public function testHttpStreamWithString(): void
    {
        $stream = Http::stream('hello');
        static::assertInstanceOf(StreamInterface::class, $stream);
        static::assertSame('hello', (string) $stream);
    }

    public function testHttpStreamWithArray(): void
    {
        $stream = Http::stream(['a', 'b']);
        static::assertInstanceOf(StreamInterface::class, $stream);
    }

    public function testHttpStreamWithStreamInterface(): void
    {
        $inner  = Http::stream('data');
        $stream = Http::stream($inner);
        static::assertSame($inner, $stream);
    }

    public function testHttpStreamWithObjectToString(): void
    {
        $obj = new class {
            public function __toString(): string
            {
                return 'from object';
            }
        };
        $stream = Http::stream($obj);
        static::assertInstanceOf(StreamInterface::class, $stream);
        static::assertSame('from object', (string) $stream);
    }

    public function testHttpStreamWithInvalidTypeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Http::stream(new \stdClass());
    }

    // -----------------------------------------------------------------------
    // Request – additional uncovered methods
    // -----------------------------------------------------------------------

    public function testRequestDelete(): void
    {
        $req = Request::delete('http://example.com/resource/1');
        static::assertSame(Http::DELETE, $req->getMethod());
        static::assertSame('http://example.com/resource/1', (string) $req->getUri());
    }

    public function testRequestDeleteWithParams(): void
    {
        $req = Request::delete('http://example.com/items', ['id' => 5]);
        static::assertSame(Http::DELETE, $req->getMethod());
        static::assertStringContainsString('id=5', (string) $req->getUri());
    }

    public function testRequestHead(): void
    {
        $req = Request::head('http://example.com/');
        static::assertSame(Http::HEAD, $req->getMethod());
    }

    public function testRequestFollowRedirects(): void
    {
        $req = Request::get('http://example.com/');
        $req = $req->followRedirects(true);
        // Internal state is accessible via getIterator()
        $iter = $req->getIterator();
        static::assertTrue($iter['follow_redirects']);
        static::assertSame(Request::MAX_REDIRECTS_DEFAULT, $iter['max_redirects']);
    }

    public function testRequestDoNotFollowRedirects(): void
    {
        $req  = Request::get('http://example.com/')->doNotFollowRedirects();
        $iter = $req->getIterator();
        static::assertFalse($iter['follow_redirects']);
        static::assertSame(0, $iter['max_redirects']);
    }

    public function testRequestDisableKeepAlive(): void
    {
        $req = Request::get('http://example.com/')->disableKeepAlive();
        // disableKeepAlive modifies in-place; verify via _curlPrep raw headers
        $req->_curlPrep();
        static::assertStringContainsString('Connection: close', $req->getRawHeaders());
    }

    public function testRequestEnableKeepAlive(): void
    {
        $req = Request::get('http://example.com/')->enableKeepAlive(60);
        $req->_curlPrep();
        static::assertStringContainsString('Keep-Alive: 60', $req->getRawHeaders());
    }

    public function testRequestEnableKeepAliveZeroThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Request::get('http://example.com/')->enableKeepAlive(0);
    }

    public function testRequestEnableKeepAliveNegativeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Request::get('http://example.com/')->enableKeepAlive(-1);
    }

    public function testRequestRetryByPossibleEncodingError(): void
    {
        $req = Request::get('http://example.com/');
        $req->enableRetryByPossibleEncodingError();
        $iter = $req->getIterator();
        static::assertTrue($iter['retry_by_possible_encoding_error']);

        $req->disableRetryByPossibleEncodingError();
        $iter = $req->getIterator();
        static::assertFalse($iter['retry_by_possible_encoding_error']);
    }

    public function testRequestWithMethod(): void
    {
        $req = (new Request('GET'))->withUriFromString('http://example.com/');
        $req = $req->withMethod('PATCH');
        static::assertSame('PATCH', $req->getMethod());
    }

    public function testRequestWithProtocolVersion(): void
    {
        $req = (new Request('GET'))->withUriFromString('http://example.com/');
        $req = $req->withProtocolVersion('1.0');
        static::assertSame('1.0', $req->getProtocolVersion());
    }

    public function testRequestDisableAutoParsing(): void
    {
        $req = Request::get('http://example.com/')->disableAutoParsing();
        static::assertFalse($req->isAutoParse());
    }

    public function testRequestEnableAutoParsing(): void
    {
        $req = Request::get('http://example.com/')->disableAutoParsing()->enableAutoParsing();
        static::assertTrue($req->isAutoParse());
    }

    public function testRequestEnableStrictSSL(): void
    {
        $req = Request::get('http://example.com/')->enableStrictSSL();
        static::assertTrue($req->isStrictSSL());
    }

    public function testRequestIsJson(): void
    {
        $req = (new Request(Http::POST, Mime::JSON))->withUriFromString('http://example.com/');
        static::assertTrue($req->isJson());
    }

    public function testRequestIsNotJson(): void
    {
        $req = Request::get('http://example.com/');
        static::assertFalse($req->isJson());
    }

    public function testRequestGetIterator(): void
    {
        $req  = Request::get('http://example.com/');
        $iter = $req->getIterator();
        static::assertInstanceOf(\ArrayObject::class, $iter);
    }

    public function testRequestBuildUserAgent(): void
    {
        $req       = Request::get('http://example.com/');
        $userAgent = $req->buildUserAgent();
        static::assertIsString($userAgent);
        static::assertStringContainsString('Http/PhpClient', $userAgent);
    }

    public function testRequestExpectsHelpers(): void
    {
        $req = Request::get('http://example.com/');
        static::assertSame(Mime::JSON, $req->expectsJson()->getExpectedType());
        static::assertSame(Mime::XML, $req->expectsXml()->getExpectedType());
        static::assertSame(Mime::CSV, $req->expectsCsv()->getExpectedType());
        static::assertSame(Mime::HTML, $req->expectsHtml()->getExpectedType());
        static::assertSame(Mime::FORM, $req->expectsForm()->getExpectedType());
        static::assertSame(Mime::PLAIN, $req->expectsPlain()->getExpectedType());
        static::assertSame(Mime::PLAIN, $req->expectsText()->getExpectedType());
        static::assertSame(Mime::JS, $req->expectsJs()->getExpectedType());
        static::assertSame(Mime::JS, $req->expectsJavascript()->getExpectedType());
        static::assertSame(Mime::UPLOAD, $req->expectsUpload()->getExpectedType());
        static::assertSame(Mime::XHTML, $req->expectsXhtml()->getExpectedType());
    }

    public function testRequestHasClientSideCert(): void
    {
        $req = Request::get('http://example.com/')->clientSideCertAuth('/path/cert.pem', '/path/key.pem', 'pass');
        static::assertTrue($req->hasClientSideCert());
    }

    public function testRequestHasNoClientSideCert(): void
    {
        $req = Request::get('http://example.com/');
        static::assertFalse($req->hasClientSideCert());
    }

    public function testRequestHasNoTimeout(): void
    {
        $req = Request::get('http://example.com/');
        static::assertFalse($req->hasTimeout());
    }

    public function testRequestWithTimeout(): void
    {
        $req = Request::get('http://example.com/')->withTimeout(5);
        static::assertTrue($req->hasTimeout());
    }

    public function testRequestNeverSerializePayload(): void
    {
        $req = Request::get('http://example.com/')->neverSerializePayload();
        static::assertSame(Request::SERIALIZE_PAYLOAD_NEVER, $req->getSerializePayloadMethod());
    }

    // -----------------------------------------------------------------------
    // Setup
    // -----------------------------------------------------------------------

    public function testSetupRegisterGlobalErrorHandlerInvalidThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Setup::registerGlobalErrorHandler('not_a_callable_or_logger');
    }

    public function testSetupRegisterGlobalErrorHandlerCallable(): void
    {
        Setup::registerGlobalErrorHandler(static function ($error) {
        });
        static::assertIsCallable(Setup::getGlobalErrorHandler());
        // restore
        Setup::reset();
    }

    public function testSetupHasParserRegistered(): void
    {
        Setup::initMimeHandlers();
        static::assertTrue(Setup::hasParserRegistered(Mime::JSON));
        static::assertFalse(Setup::hasParserRegistered('application/x-unknown-type'));
    }

    // -----------------------------------------------------------------------
    // Mime
    // -----------------------------------------------------------------------

    public function testMimeGetFullMimeKnown(): void
    {
        static::assertSame(Mime::JSON, \Httpful\Mime::getFullMime('json'));
        static::assertSame(Mime::XML, \Httpful\Mime::getFullMime('xml'));
        static::assertSame(Mime::CSV, \Httpful\Mime::getFullMime('csv'));
    }

    public function testMimeGetFullMimeUnknown(): void
    {
        static::assertSame('application/x-custom', \Httpful\Mime::getFullMime('application/x-custom'));
    }

    public function testMimeSupportsMimeType(): void
    {
        static::assertTrue(\Httpful\Mime::supportsMimeType('json'));
        static::assertFalse(\Httpful\Mime::supportsMimeType('application/x-unknown'));
    }
}
