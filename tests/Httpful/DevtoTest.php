<?php

declare(strict_types=1);

namespace Httpful\tests;

use Httpful\ClientPromise;
use Httpful\Mime;
use Httpful\Request;
use Httpful\Response;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class DevtoTest extends TestCase
{
    public function testSimpleCall(): void
    {
        if (\defined('WITHOUT_SERVER') && \WITHOUT_SERVER) {
            $this->markTestSkipped('The local HTTP fixture server is unavailable on this platform.');
        }

        // Use the existing loopback test server rather than an unreliable remote API.
        $fixtureBaseUrl = 'http://' . \TEST_SERVER . '/';

        /** @var list<array{id: int}> $articles */
        $articles = Request::get($fixtureBaseUrl . 'devto-articles.json')
            ->withExpectedType(Mime::JSON)
            ->send()
            ->getRawBody();

        static::assertCount(2, $articles);

        $client = new ClientPromise();

        foreach ($articles as $article) {
            $request = Request::get($fixtureBaseUrl . 'devto-article-' . $article['id'] . '.json')
                ->withExpectedType(Mime::JSON);

            $client->sendAsyncRequest($request);
        }

        /** @var list<Response> $results */
        $results = [];
        $promise = $client->getPromise();

        $promise->then(static function (Response $response, Request $request) use (&$results): void {
            $results[] = $response;
        });

        $promise->wait();

        static::assertCount(2, $results);

        $receivedIds = [];

        foreach ($results as $response) {
            static::assertSame(200, $response->getStatusCode());

            /** @var array{id: int} $article */
            $article = $response->getRawBody();
            $receivedIds[] = $article['id'];
        }

        \sort($receivedIds);

        static::assertSame([101, 102], $receivedIds);
    }
}
