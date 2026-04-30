<?php

declare(strict_types=1);

namespace Httpful\tests;

use Httpful\ClientPromise;
use Httpful\Request;
use Httpful\Response;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class ClientPromiseTest extends TestCase
{
    private static function localFixtureUrl(string $path): string
    {
        return 'http://' . \TEST_SERVER . '/' . ltrim($path, '/');
    }

    public function testGet()
    {
        $client = new ClientPromise();

        $request = (new Request('GET'))
            ->withUriFromString(self::localFixtureUrl('foo.txt'));

        $promise = $client->sendAsyncRequest($request);

        /** @var Response $result */
        $result = null;
        $promise->then(static function (Response $response, Request $request) use (&$result) {
            $result = $response;
        });

        $promise->wait();

        static::assertInstanceOf(Response::class, $result);

        if (\method_exists(__CLASS__, 'assertStringContainsString')) {
            static::assertStringContainsString('Foobar', (string) $result);
        } else {
            static::assertContains('Foobar', (string) $result);
        }
    }

    public function testGetMultiPromise()
    {
        $client = new ClientPromise();

        $client->add_get(self::localFixtureUrl('foo.txt'));
        $client->add_get(self::localFixtureUrl('test.json'));

        $promise = $client->getPromise();

        /** @var Response[] $results */
        $results = [];
        $promise->then(static function (Response $response, Request $request) use (&$results) {
            $results[] = $response;
        });

        $promise->wait();

        static::assertCount(2, $results);
        $bodies = array_map('strval', $results);
        sort($bodies);

        if (\method_exists(__CLASS__, 'assertStringContainsString')) {
            static::assertStringContainsString('Foobar', $bodies[0] . $bodies[1]);
            static::assertStringContainsString('"foo": "bar"', $bodies[0] . $bodies[1]);
        } else {
            static::assertContains('Foobar', $bodies[0] . $bodies[1]);
            static::assertContains('"foo": "bar"', $bodies[0] . $bodies[1]);
        }
    }

    public function testRequestSendAsyncHelper()
    {
        $request = Request::get(self::localFixtureUrl('foo.txt'));

        $promise = $request->sendAsync();

        /** @var Response $result */
        $result = null;
        $promise->then(static function (Response $response, Request $request) use (&$result) {
            $result = $response;
        });

        $promise->wait();

        static::assertInstanceOf(Response::class, $result);
        static::assertStringContainsString('Foobar', (string) $result);
    }
}
