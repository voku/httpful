<?php

declare(strict_types=1);

namespace Httpful\tests;

use Httpful\Client;
use Httpful\Http;
use Httpful\Mime;
use Httpful\Request;
use PHPUnit\Framework\TestCase;

/**
 * Coverage for the QUERY method (RFC 10008).
 *
 * QUERY is the first method httpful supports that is safe and idempotent while
 * still carrying a request body, so the classification helpers and the body
 * handling are both worth pinning down.
 *
 * @internal
 */
final class HttpQueryMethodTest extends TestCase
{
    public function testQueryIsAKnownMethod()
    {
        static::assertSame('QUERY', Http::QUERY);
        static::assertContains(Http::QUERY, Http::allMethods());
    }

    public function testQueryIsSafe()
    {
        static::assertContains(Http::QUERY, Http::safeMethods());
        static::assertTrue(Http::isSafeMethod(Http::QUERY));
        static::assertFalse(Http::isUnsafeMethod(Http::QUERY));
    }

    public function testQueryIsIdempotent()
    {
        static::assertContains(Http::QUERY, Http::idempotentMethods());
        static::assertTrue(Http::isIdempotent(Http::QUERY));
        static::assertFalse(Http::isNotIdempotent(Http::QUERY));
    }

    public function testRequestQueryBuildsAQueryRequest()
    {
        $request = Request::query('http://example.com/search');

        static::assertSame(Http::QUERY, $request->getMethod());
        static::assertSame('http://example.com/search', (string) $request->getUri());
    }

    public function testRequestQueryCarriesThePayloadAndContentType()
    {
        $request = Request::query(
            'http://example.com/search',
            ['q' => 'httpful'],
            Mime::JSON
        );

        static::assertSame(Http::QUERY, $request->getMethod());
        // The payload is only serialized, and the outgoing headers only
        // rendered, while preparing the curl handle.
        $request->_curlPrep();
        static::assertSame('{"q":"httpful"}', $request->getSerializedPayload());
        // RFC 10008: the server must reject a QUERY whose Content-Type is
        // missing or inconsistent with the content, so the request has to carry
        // both the type and a length.
        static::assertStringContainsString("\r\nContent-Type: application/json\r\n", $request->getRawHeaders());
        static::assertStringContainsString("\r\nContent-Length: 15\r\n", $request->getRawHeaders());
    }

    public function testRequestQueryAcceptsAUriInstance()
    {
        $request = Request::query(new \Httpful\Uri('http://example.com/search'));

        static::assertSame(Http::QUERY, $request->getMethod());
        static::assertSame('http://example.com/search', (string) $request->getUri());
    }

    public function testClientQueryRequestDelegatesToRequestQuery()
    {
        $request = Client::query_request(
            'http://example.com/search',
            'q=httpful',
            Mime::FORM
        );

        static::assertInstanceOf(Request::class, $request);
        static::assertSame(Http::QUERY, $request->getMethod());

        $request->_curlPrep();
        static::assertSame('q=httpful', $request->getSerializedPayload());
    }

    public function testQueryRequestCanBePreparedForCurl()
    {
        $request = Request::query('http://example.com/search', ['q' => 'httpful'], Mime::JSON);

        // _curlPrep() is where an unknown method would surface: the request line
        // is rendered here and the method is handed to CURLOPT_CUSTOMREQUEST.
        $request->_curlPrep();

        static::assertStringStartsWith('QUERY /search HTTP/', $request->getRawHeaders());
    }
}
