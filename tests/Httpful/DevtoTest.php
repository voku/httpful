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
        // Exercise real synchronous and asynchronous HTTP requests without
        // relying on dev.to rate limits, availability, or response content.
        $endpoint = 'http://' . \TEST_SERVER . '/devto-api.php';

        $articles = Request::get($endpoint . '?page=1&per_page=2&username=suckup_de')
            ->withExpectedType(Mime::JSON)
            ->send()
            ->getRawBody();

        self::assertIsArray($articles);
        self::assertCount(2, $articles);

        $client = new ClientPromise();

        foreach ($articles as $article) {
            self::assertIsArray($article);
            self::assertArrayHasKey('id', $article);

            $client->sendAsyncRequest(
                Request::get($endpoint . '?id=' . $article['id'])->withExpectedType(Mime::JSON)
            );
        }

        /** @var list<int> $receivedIds */
        $receivedIds = [];

        $client->getPromise()->then(
            static function (Response $response, Request $request) use (&$receivedIds): void {
                self::assertSame(200, $response->getStatusCode());

                $body = $response->getRawBody();
                self::assertIsArray($body);
                self::assertArrayHasKey('id', $body);

                $receivedIds[] = $body['id'];
            }
        );

        $client->getPromise()->wait();

        \sort($receivedIds);
        self::assertSame([101, 102], $receivedIds);
    }
}
