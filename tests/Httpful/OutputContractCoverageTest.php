<?php

declare(strict_types=1);

namespace Httpful\tests;

use Httpful\Factory;
use Httpful\Handlers\DefaultMimeHandler;
use Httpful\Handlers\HtmlMimeHandler;
use Httpful\Mime;
use Httpful\Request;
use Httpful\Response;
use Httpful\ServerRequest;
use Httpful\Setup;
use Httpful\Stream;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class OutputContractCoverageTest extends TestCase
{
    protected function tearDown(): void
    {
        Setup::reset();
    }

    public function testFactoryCreateRequestWithArrayBodyUsesSerializer(): void
    {
        $request = (new Factory())->createRequest(
            'POST',
            'https://example.com/users',
            Mime::FORM,
            ['name' => 'Alice', 'role' => 'admin']
        );

        static::assertSame('POST', $request->getMethod());
        static::assertSame('https://example.com/users', (string) $request->getUri());
        static::assertStringContainsString('Alice', (string) $request->getBody());
        static::assertStringContainsString('role', (string) $request->getBody());
    }

    public function testSetupKeepsExistingMimeHandlerRegistration(): void
    {
        $handler = new class extends DefaultMimeHandler {
        };

        Setup::reset();
        Setup::registerMimeHandler(Mime::JSON, $handler);
        Setup::initMimeHandlers();

        static::assertSame($handler, Setup::setupGlobalMimeType(Mime::JSON));
    }

    public function testSetupUsesRegisteredGlobalMimeHandlerAsFallback(): void
    {
        $handler = new class extends DefaultMimeHandler {
        };

        Setup::reset();
        Setup::registerGlobalMimeHandler($handler);

        static::assertSame($handler, Setup::setupGlobalMimeType('application/x-custom'));
    }

    public function testHtmlMimeHandlerConvertsNonUtf8Input(): void
    {
        $html = '<html><body><p>' . \hex2bin('e9') . '</p></body></html>';
        $result = (new HtmlMimeHandler())->parse($html);

        static::assertSame('é', $result->findOne('p')->text());
    }

    public function testServerRequestWithParsedBodyRejectsScalarPayload(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('array or null');

        (new ServerRequest('POST'))->withParsedBody('invalid');
    }

    public function testResponseStringifiesRawStringWhenBodyIsEmpty(): void
    {
        $response = (new Response())->withBody(Stream::create(''));
        self::setPrivateProperty($response, 'raw_body', 'fallback');

        static::assertSame('fallback', (string) $response);
    }

    public function testResponseNormalizesScalarHeaderValuesToArray(): void
    {
        $response = new Response();
        $headers = $response->getHeadersObject();

        self::setPrivateProperty($headers, 'data', ['x-test' => " \t value \t"]);
        self::setPrivateProperty($headers, 'keys', ['x-test' => 'X-Test']);

        static::assertSame(['value'], $response->getHeader('X-Test'));
    }

    public function testResponseFallsBackToDefaultProtocolVersionWhenMetadataIsMissing(): void
    {
        $response = new Response();
        self::setPrivateProperty($response, 'meta_data', []);

        static::assertSame('1.1', $response->getProtocolVersion());
    }

    public function testResponseGetErrorMessageCoversInformationalAndUnknownStatuses(): void
    {
        static::assertStringContainsString(
            'Informational response (100 Continue)',
            (new Response())->withStatus(100)->getErrorMessage()
        );

        static::assertSame(
            'Unknown response status (99 ).',
            (new Response())->withStatus(99)->getErrorMessage()
        );
    }

    public function testResponseUsesParseCallbackContract(): void
    {
        $request = Request::get('https://example.com')
            ->withParseCallback(static function ($body): array {
                return [
                    'body' => (string) $body,
                    'type' => \get_debug_type($body),
                ];
            });

        $response = new Response(
            'payload',
            "HTTP/1.1 200 OK\r\nContent-Type: text/plain\r\n\r\n",
            $request
        );

        static::assertSame(
            [
                'body' => 'payload',
                'type' => Stream::class,
            ],
            $response->getRawBody()
        );
    }

    /**
     * @param mixed $value
     */
    private static function setPrivateProperty(object $object, string $property, $value): void
    {
        $reflection = new \ReflectionProperty($object, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($object, $value);
    }
}
