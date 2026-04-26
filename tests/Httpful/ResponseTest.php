<?php

declare(strict_types=1);

namespace Httpful\tests;

use Httpful\Factory;
use Httpful\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

/**
 * @internal
 */
final class ResponseTest extends TestCase
{
    public function testDefaultConstructor()
    {
        $r = new Response();
        static::assertSame(200, $r->getStatusCode());
        static::assertSame('1.1', $r->getProtocolVersion());
        static::assertSame('OK', $r->getReasonPhrase());
        static::assertSame([], $r->getHeaders());
        static::assertInstanceOf(StreamInterface::class, $r->getBody());
        static::assertSame('', (string) $r->getBody());
        static::assertFalse($r->hasBody());
    }

    public function testCanConstructWithStatusCode()
    {
        $r = (new Response())->withStatus(404);
        static::assertSame(404, $r->getStatusCode());
        static::assertSame('Not Found', $r->getReasonPhrase());
    }

    public function testCanConstructWithUndefinedStatusCode()
    {
        $r = (new Response())->withStatus(999);
        static::assertSame(999, $r->getStatusCode());
        static::assertSame('', $r->getReasonPhrase());
    }

    public function testConstructorDoesNotReadStreamBody()
    {
        $body = $this->getMockBuilder(StreamInterface::class)->getMock();
        $body->expects(static::never())
            ->method('__toString');

        $r = (new Response())->withBody($body);
        static::assertSame($body, $r->getBody());
    }

    public function testStatusCanBeNumericString()
    {
        $r = (new Response())->withStatus(404);
        $r2 = $r->withStatus('201');
        static::assertSame(404, $r->getStatusCode());
        static::assertSame('Not Found', $r->getReasonPhrase());
        static::assertSame(201, $r2->getStatusCode());
        static::assertSame('Created', $r2->getReasonPhrase());
    }

    public function testCanConstructWithHeaders()
    {
        $r = (new Response())->withHeaders(['Foo' => 'Bar']);
        static::assertSame(['Foo' => ['Bar']], $r->getHeaders());
        static::assertSame('Bar', $r->getHeaderLine('Foo'));
        static::assertSame(['Bar'], $r->getHeader('Foo'));
    }

    public function testCanConstructWithHeadersAsArray()
    {
        $r = new Response('', ['Foo' => ['baz', 'bar']]);
        static::assertSame(['Foo' => ['baz', 'bar']], $r->getHeaders());
        static::assertSame('baz, bar', $r->getHeaderLine('Foo'));
        static::assertSame(['baz', 'bar'], $r->getHeader('Foo'));
    }

    public function testCanConstructWithBody()
    {
        $r = new Response('baz');
        static::assertInstanceOf(StreamInterface::class, $r->getBody());
        static::assertSame('baz', (string) $r->getBody());
    }

    public function testConstructorPreservesGenericStreamBodyWithoutParsingContext()
    {
        $body = $this->getMockBuilder(StreamInterface::class)->getMock();
        $body->expects(static::never())
            ->method('__toString');

        $r = new Response($body);
        static::assertSame($body, $r->getBody());
    }

    public function testNullBody()
    {
        $r = new Response(null);
        static::assertInstanceOf(StreamInterface::class, $r->getBody());
        static::assertSame('', (string) $r->getBody());
    }

    public function testFalseyBody()
    {
        $r = new Response('0');
        static::assertInstanceOf(StreamInterface::class, $r->getBody());
        static::assertSame('0', (string) $r->getBody());
    }

    public function testCanConstructWithReason()
    {
        $r = (new Response())->withStatus(200, 'bar');
        static::assertSame('bar', $r->getReasonPhrase());

        $r = (new Response())->withStatus(200, '0');
        static::assertSame('0', $r->getReasonPhrase(), 'Falsey reason works');
    }

    public function testCanConstructWithProtocolVersion()
    {
        $r = (new Response())->withProtocolVersion('1000');
        static::assertSame('1000', $r->getProtocolVersion());
    }

    public function testWithStatusCodeAndNoReason()
    {
        $r = (new Response())->withStatus(201);
        static::assertSame(201, $r->getStatusCode());
        static::assertSame('Created', $r->getReasonPhrase());
    }

    public function testWithStatusCodeAndReason()
    {
        $r = (new Response())->withStatus(201, 'Foo');
        static::assertSame(201, $r->getStatusCode());
        static::assertSame('Foo', $r->getReasonPhrase());

        $r = (new Response())->withStatus(201, '0');
        static::assertSame(201, $r->getStatusCode());
        static::assertSame('0', $r->getReasonPhrase(), 'Falsey reason works');
    }

    public function testWithProtocolVersion()
    {
        $r = (new Response())->withProtocolVersion('1000');
        static::assertSame('1000', $r->getProtocolVersion());
    }

    public function testSameInstanceWhenSameProtocol()
    {
        $r = new Response();
        static::assertEquals($r, $r->withProtocolVersion('1.1'));
    }

    public function testWithBody()
    {
        $b = (new Factory())->createStream('0');
        $r = (new Response())->withBody($b);
        static::assertInstanceOf(StreamInterface::class, $r->getBody());
        static::assertSame('0', (string) $r->getBody());
    }

    public function testSameInstanceWhenSameBody()
    {
        $r = new Response();
        $b = $r->getBody();
        static::assertEquals($r, $r->withBody($b));
    }

    public function testWithHeader()
    {
        $r = new Response(200, ['Foo' => 'Bar']);
        $r2 = $r->withHeader('baZ', 'Bam');
        static::assertSame(['Foo' => ['Bar']], $r->getHeaders());
        static::assertSame(['Foo' => ['Bar'], 'baZ' => ['Bam']], $r2->getHeaders());
        static::assertSame('Bam', $r2->getHeaderLine('baz'));
        static::assertSame(['Bam'], $r2->getHeader('baz'));
    }

    public function testWithHeaderAsArray()
    {
        $r = new Response(200, ['Foo' => 'Bar']);
        $r2 = $r->withHeader('baZ', ['Bam', 'Bar']);
        static::assertSame(['Foo' => ['Bar']], $r->getHeaders());
        static::assertSame(['Foo' => ['Bar'], 'baZ' => ['Bam', 'Bar']], $r2->getHeaders());
        static::assertSame('Bam, Bar', $r2->getHeaderLine('baz'));
        static::assertSame(['Bam', 'Bar'], $r2->getHeader('baz'));
    }

    public function testWithHeaderReplacesDifferentCase()
    {
        $r = new Response(200, ['Foo' => 'Bar']);
        $r2 = $r->withHeader('foO', 'Bam');
        static::assertSame(['Foo' => ['Bar']], $r->getHeaders());
        static::assertSame(['foO' => ['Bam']], $r2->getHeaders());
        static::assertSame('Bam', $r2->getHeaderLine('foo'));
        static::assertSame(['Bam'], $r2->getHeader('foo'));
    }

    public function testWithAddedHeader()
    {
        $r = new Response(200, ['Foo' => 'Bar']);
        $r2 = $r->withAddedHeader('foO', 'Baz');
        static::assertSame(['Foo' => ['Bar']], $r->getHeaders());
        static::assertSame(['foO' => ['Bar', 'Baz']], $r2->getHeaders());
        static::assertSame('Bar, Baz', $r2->getHeaderLine('foo'));
        static::assertSame(['Bar', 'Baz'], $r2->getHeader('foo'));
    }

    public function testWithAddedHeaderAsArray()
    {
        $r = new Response(200, ['Foo' => 'Bar']);
        $r2 = $r->withAddedHeader('foO', ['Baz', 'Bam']);
        static::assertSame(['Foo' => ['Bar']], $r->getHeaders());
        static::assertSame(['foO' => ['Bar', 'Baz', 'Bam']], $r2->getHeaders());
        static::assertSame('Bar, Baz, Bam', $r2->getHeaderLine('foo'));
        static::assertSame(['Bar', 'Baz', 'Bam'], $r2->getHeader('foo'));
    }

    public function testWithAddedHeaderThatDoesNotExist()
    {
        $r = new Response(200, ['Foo' => 'Bar']);
        $r2 = $r->withAddedHeader('nEw', 'Baz');
        static::assertSame(['Foo' => ['Bar']], $r->getHeaders());
        static::assertSame(['Foo' => ['Bar'], 'nEw' => ['Baz']], $r2->getHeaders());
        static::assertSame('Baz', $r2->getHeaderLine('new'));
        static::assertSame(['Baz'], $r2->getHeader('new'));
    }

    public function testWithoutHeaderThatExists()
    {
        $r = new Response(200, ['Foo' => 'Bar', 'Baz' => 'Bam']);
        $r2 = $r->withoutHeader('foO');
        static::assertTrue($r->hasHeader('foo'));
        static::assertSame(['Foo' => ['Bar'], 'Baz' => ['Bam']], $r->getHeaders());
        static::assertFalse($r2->hasHeader('foo'));
        static::assertSame(['Baz' => ['Bam']], $r2->getHeaders());
    }

    public function testWithoutHeaderThatDoesNotExist()
    {
        $r = new Response(200, ['Baz' => 'Bam']);
        $r2 = $r->withoutHeader('foO');
        static::assertEquals($r, $r2);
        static::assertFalse($r2->hasHeader('foo'));
        static::assertSame(['Baz' => ['Bam']], $r2->getHeaders());
    }

    public function testSameInstanceWhenRemovingMissingHeader()
    {
        $r = new Response();
        static::assertEquals($r, $r->withoutHeader('foo'));
    }

    public function trimmedHeaderValues()
    {
        return [
            [new Response(200, ['OWS' => " \t \tFoo\t \t "])],
            [(new Response())->withHeader('OWS', " \t \tFoo\t \t ")],
            [(new Response())->withAddedHeader('OWS', " \t \tFoo\t \t ")],
        ];
    }

    /**
     * @dataProvider trimmedHeaderValues
     *
     * @param mixed $r
     */
    public function testHeaderValuesAreTrimmed($r)
    {
        static::assertSame(['OWS' => ['Foo']], $r->getHeaders());
        static::assertSame('Foo', $r->getHeaderLine('OWS'));
        static::assertSame(['Foo'], $r->getHeader('OWS'));
    }

    // -----------------------------------------------------------------------
    // Status-range helpers
    // -----------------------------------------------------------------------

    public function testIsInformational()
    {
        $r = (new Response())->withStatus(100);
        static::assertTrue($r->isInformational());
        static::assertFalse($r->isSuccess());
        static::assertFalse($r->isRedirect());
        static::assertFalse($r->isClientError());
        static::assertFalse($r->isServerError());
        static::assertFalse($r->hasErrors());
    }

    public function testIsSuccess()
    {
        foreach ([200, 201, 204] as $code) {
            $r = (new Response())->withStatus($code);
            static::assertTrue($r->isSuccess(), "Expected isSuccess() for {$code}");
            static::assertFalse($r->isInformational());
            static::assertFalse($r->isRedirect());
            static::assertFalse($r->isClientError());
            static::assertFalse($r->isServerError());
            static::assertFalse($r->hasErrors());
        }
    }

    public function testIsRedirect()
    {
        foreach ([301, 302, 307] as $code) {
            $r = (new Response())->withStatus($code);
            static::assertTrue($r->isRedirect(), "Expected isRedirect() for {$code}");
            static::assertFalse($r->isSuccess());
            static::assertFalse($r->isClientError());
            static::assertFalse($r->isServerError());
            static::assertFalse($r->hasErrors());
        }
    }

    public function testIsClientError()
    {
        foreach ([400, 401, 403, 404, 422, 429] as $code) {
            $r = (new Response())->withStatus($code);
            static::assertTrue($r->isClientError(), "Expected isClientError() for {$code}");
            static::assertTrue($r->hasErrors(), "Expected hasErrors() for {$code}");
            static::assertFalse($r->isServerError());
            static::assertFalse($r->isSuccess());
        }
    }

    public function testIsServerError()
    {
        foreach ([500, 502, 503, 504] as $code) {
            $r = (new Response())->withStatus($code);
            static::assertTrue($r->isServerError(), "Expected isServerError() for {$code}");
            static::assertTrue($r->hasErrors(), "Expected hasErrors() for {$code}");
            static::assertFalse($r->isClientError());
            static::assertFalse($r->isSuccess());
        }
    }

    // -----------------------------------------------------------------------
    // getErrorMessage()
    // -----------------------------------------------------------------------

    public function testGetErrorMessageForSuccess()
    {
        $r = (new Response())->withStatus(200);
        static::assertStringContainsString('successful', \strtolower($r->getErrorMessage()));
        static::assertStringContainsString('200', $r->getErrorMessage());
    }

    public function testGetErrorMessageForClientError()
    {
        $r = (new Response())->withStatus(404);
        $msg = $r->getErrorMessage();
        static::assertStringContainsString('404', $msg);
        static::assertStringContainsString('Not Found', $msg);
    }

    public function testGetErrorMessageForServerError()
    {
        $r = (new Response())->withStatus(500);
        $msg = $r->getErrorMessage();
        static::assertStringContainsString('500', $msg);
        static::assertStringContainsString('Internal Server Error', $msg);
        static::assertStringContainsString('server', \strtolower($msg));
    }

    public function testGetErrorMessageForRedirect()
    {
        $r = (new Response())->withStatus(301);
        $msg = $r->getErrorMessage();
        static::assertStringContainsString('301', $msg);
        static::assertStringContainsString('Redirect', $msg);
    }

    public function testGetErrorMessageFor429()
    {
        $r = (new Response())->withStatus(429);
        $msg = $r->getErrorMessage();
        static::assertStringContainsString('429', $msg);
        static::assertStringContainsString('rate limit', \strtolower($msg));
    }

    public function testGetErrorMessageFor502()
    {
        $r = (new Response())->withStatus(502);
        $msg = $r->getErrorMessage();
        static::assertStringContainsString('502', $msg);
        static::assertStringContainsString('Bad Gateway', $msg);
    }

    // -----------------------------------------------------------------------
    // debugInfo()
    // -----------------------------------------------------------------------

    public function testDebugInfoContainsStatusAndBody()
    {
        $r = new Response('hello world', "HTTP/1.1 200 OK\r\nContent-Type: text/plain\r\n\r\n");
        $debug = $r->debugInfo();
        static::assertStringContainsString('200', $debug);
        static::assertStringContainsString('Content-Type', $debug);
        static::assertStringContainsString('hello world', $debug);
        static::assertStringContainsString('Response Headers', $debug);
        static::assertStringContainsString('Response Body', $debug);
    }

    public function testDebugInfoContainsHint()
    {
        $r = (new Response())->withStatus(503);
        $debug = $r->debugInfo();
        static::assertStringContainsString('503', $debug);
        static::assertStringContainsString('Hint', $debug);
    }

    public function testDebugInfoWithRequestShowsMethodAndUrl()
    {
        $request = \Httpful\Request::get('https://example.com/api/users?page=2');
        $r = new Response('{"items":[]}', "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\n\r\n", $request);
        $debug = $r->debugInfo();

        // Request section must be present
        static::assertStringContainsString('--- Request ---', $debug);
        static::assertStringContainsString('GET', $debug);
        static::assertStringContainsString('https://example.com/api/users', $debug);
        static::assertStringContainsString('page=2', $debug);
    }

    public function testDebugInfoWithRequestShowsRequestHeaders()
    {
        $request = \Httpful\Request::get('https://example.com/ping')
            ->withHeader('Accept', 'application/json');
        $r = new Response('ok', "HTTP/1.1 200 OK\r\n\r\n", $request);
        $debug = $r->debugInfo();

        static::assertStringContainsString('Accept', $debug);
        static::assertStringContainsString('application/json', $debug);
    }

    public function testDebugInfoWithoutRequestHasNoRequestSection()
    {
        $r = new Response('ok', "HTTP/1.1 200 OK\r\n\r\n");
        $debug = $r->debugInfo();

        static::assertStringNotContainsString('--- Request ---', $debug);
    }
}
