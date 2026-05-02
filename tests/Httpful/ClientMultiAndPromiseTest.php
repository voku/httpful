<?php

declare(strict_types=1);

namespace Httpful\tests;

use Httpful\ClientMulti;
use Httpful\Curl\Curl;
use Httpful\Curl\MultiCurl;
use Httpful\Curl\MultiCurlPromise;
use Httpful\Http;
use Httpful\Mime;
use Httpful\Request;
use Httpful\Response;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ClientMulti, MultiCurl, MultiCurlPromise and additional _curlPrep paths.
 *
 * @internal
 */
final class ClientMultiAndPromiseTest extends TestCase
{
    // =========================================================================
    // MultiCurl – configuration methods (no network)
    // =========================================================================

    public function testMultiCurlConstruct(): void
    {
        $mc = new MultiCurl();
        static::assertNotNull($mc->getMultiCurl());
    }

    public function testMultiCurlCallbacks(): void
    {
        $mc = new MultiCurl();
        $s = static function () {
        };
        $e = static function () {
        };
        $c = static function () {
        };
        $b = static function () {
        };

        static::assertSame($mc, $mc->success($s));
        static::assertSame($mc, $mc->error($e));
        static::assertSame($mc, $mc->complete($c));
        static::assertSame($mc, $mc->beforeSend($b));
    }

    public function testMultiCurlSetConcurrencyAndCookies(): void
    {
        $mc = new MultiCurl();
        static::assertSame($mc, $mc->setConcurrency(5));
        static::assertSame($mc, $mc->setCookie('a', '1'));
        static::assertSame($mc, $mc->setCookies(['b' => '2', 'c' => '3']));
    }

    public function testMultiCurlSetRetry(): void
    {
        $mc = new MultiCurl();
        static::assertSame($mc, $mc->setRetry(3));
        static::assertSame($mc, $mc->setRetry(static function () {
            return false;
        }));
    }

    public function testMultiCurlAddCurl(): void
    {
        $mc = new MultiCurl();
        $curl = new Curl();
        $result = $mc->addCurl($curl);
        static::assertSame($mc, $result);
    }

    public function testMultiCurlAddDownloadWithCallable(): void
    {
        $mc = new MultiCurl();
        $curl = new Curl();
        $called = false;
        $result = $mc->addDownload($curl, static function () use (&$called) {
            $called = true;
        });
        static::assertSame($curl, $result);
        static::assertIsResource($curl->getFileHandle());
    }

    public function testMultiCurlAddDownloadToFile(): void
    {
        $mc = new MultiCurl();
        $curl = new Curl();
        $tmpFile = \tempnam(\sys_get_temp_dir(), 'mc_dl_');
        $result = $mc->addDownload($curl, $tmpFile);
        static::assertSame($curl, $result);
        @unlink($tmpFile . '.pccdownload');
        @unlink($tmpFile);
    }

    public function testMultiCurlStartEmpty(): void
    {
        $mc = new MultiCurl();
        // With no curls queued, start() should complete immediately
        $result = $mc->start();
        static::assertSame($mc, $result);
    }

    public function testMultiCurlStartIdempotent(): void
    {
        $mc = new MultiCurl();
        $first = $mc->start();
        static::assertSame($mc, $first);
        // After first start, isStarted is reset to false, so second call also runs
        $second = $mc->start();
        static::assertSame($mc, $second);
    }

    public function testMultiCurlClose(): void
    {
        $mc = new MultiCurl();
        $mc->close(); // should not throw
        static::assertTrue(true);
    }

    // =========================================================================
    // MultiCurlPromise
    // =========================================================================

    public function testMultiCurlPromiseGetState(): void
    {
        $mc = new MultiCurl();
        $promise = new MultiCurlPromise($mc);
        static::assertSame(\Http\Promise\Promise::PENDING, $promise->getState());
    }

    public function testMultiCurlPromiseThenSetsCallbacks(): void
    {
        $mc = new MultiCurl();
        $promise = new MultiCurlPromise($mc);
        $completed = false;
        $rejected = false;

        $newPromise = $promise->then(
            static function () use (&$completed) {
                $completed = true;
            },
            static function () use (&$rejected) {
                $rejected = true;
            }
        );

        static::assertInstanceOf(MultiCurlPromise::class, $newPromise);
        static::assertSame(\Http\Promise\Promise::PENDING, $newPromise->getState());
    }

    public function testMultiCurlPromiseThenWithNoCallbacks(): void
    {
        $mc = new MultiCurl();
        $promise = new MultiCurlPromise($mc);
        // Both callbacks null
        $newPromise = $promise->then(null, null);
        static::assertInstanceOf(MultiCurlPromise::class, $newPromise);
    }

    public function testMultiCurlPromiseWaitUnwrapFalse(): void
    {
        $mc = new MultiCurl();
        $promise = new MultiCurlPromise($mc);
        // wait(false) calls start() with no handles, should complete immediately and return null
        $result = $promise->wait(false);
        static::assertNull($result);
        static::assertSame(\Http\Promise\Promise::FULFILLED, $promise->getState());
    }

    public function testMultiCurlPromiseWaitUnwrapTrue(): void
    {
        $mc = new MultiCurl();
        $promise = new MultiCurlPromise($mc);
        // wait(true) calls start() with no handles and returns the MultiCurl instance
        $result = $promise->wait(true);
        static::assertSame($mc, $result);
        static::assertSame(\Http\Promise\Promise::FULFILLED, $promise->getState());
    }

    // =========================================================================
    // ClientMulti – constructor and add_* methods (no network, _curlPrep only)
    // =========================================================================

    public function testClientMultiConstruct(): void
    {
        $cm = new ClientMulti();
        static::assertInstanceOf(MultiCurl::class, $cm->curlMulti);
    }

    public function testClientMultiConstructWithCallbacks(): void
    {
        $onSuccess = static function () {
        };
        $onComplete = static function () {
        };
        $cm = new ClientMulti($onSuccess, $onComplete);
        static::assertInstanceOf(MultiCurl::class, $cm->curlMulti);
    }

    public function testClientMultiAddGet(): void
    {
        $cm = new ClientMulti();
        $result = $cm->add_get('http://localhost:1349/');
        static::assertSame($cm, $result);
    }

    public function testClientMultiAddGetWithParams(): void
    {
        $cm = new ClientMulti();
        $result = $cm->add_get('http://localhost:1349/', ['q' => 'test']);
        static::assertSame($cm, $result);
    }

    public function testClientMultiAddGetJson(): void
    {
        $cm = new ClientMulti();
        $result = $cm->add_get_json('http://localhost:1349/');
        static::assertSame($cm, $result);
    }

    public function testClientMultiAddGetForm(): void
    {
        $cm = new ClientMulti();
        $result = $cm->add_get_form('http://localhost:1349/');
        static::assertSame($cm, $result);
    }

    public function testClientMultiAddGetDom(): void
    {
        $cm = new ClientMulti();
        $result = $cm->add_get_dom('http://localhost:1349/');
        static::assertSame($cm, $result);
    }

    public function testClientMultiAddGetXml(): void
    {
        $cm = new ClientMulti();
        $result = $cm->get_xml('http://localhost:1349/');
        static::assertSame($cm, $result);
    }

    public function testClientMultiAddHtml(): void
    {
        $cm = new ClientMulti();
        $result = $cm->add_html('http://localhost:1349/');
        static::assertSame($cm, $result);
    }

    public function testClientMultiAddHead(): void
    {
        $cm = new ClientMulti();
        $result = $cm->add_head('http://localhost:1349/');
        static::assertSame($cm, $result);
    }

    public function testClientMultiAddOptions(): void
    {
        $cm = new ClientMulti();
        $result = $cm->add_options('http://localhost:1349/');
        static::assertSame($cm, $result);
    }

    public function testClientMultiAddPost(): void
    {
        $cm = new ClientMulti();
        $result = $cm->add_post('http://localhost:1349/', ['key' => 'value']);
        static::assertSame($cm, $result);
    }

    public function testClientMultiAddPostJson(): void
    {
        $cm = new ClientMulti();
        $result = $cm->add_post_json('http://localhost:1349/', ['key' => 'value']);
        static::assertSame($cm, $result);
    }

    public function testClientMultiAddPostForm(): void
    {
        $cm = new ClientMulti();
        $result = $cm->add_post_form('http://localhost:1349/', ['key' => 'value']);
        static::assertSame($cm, $result);
    }

    public function testClientMultiAddPostXml(): void
    {
        $cm = new ClientMulti();
        $result = $cm->add_post_xml('http://localhost:1349/', '<root><item>1</item></root>');
        static::assertSame($cm, $result);
    }

    public function testClientMultiAddPostDom(): void
    {
        $cm = new ClientMulti();
        $result = $cm->add_post_dom('http://localhost:1349/');
        static::assertSame($cm, $result);
    }

    public function testClientMultiAddPatch(): void
    {
        $cm = new ClientMulti();
        $result = $cm->add_patch('http://localhost:1349/', ['key' => 'value']);
        static::assertSame($cm, $result);
    }

    public function testClientMultiAddPut(): void
    {
        $cm = new ClientMulti();
        $result = $cm->add_put('http://localhost:1349/', ['key' => 'value']);
        static::assertSame($cm, $result);
    }

    public function testClientMultiAddDelete(): void
    {
        $cm = new ClientMulti();
        $result = $cm->add_delete('http://localhost:1349/');
        static::assertSame($cm, $result);
    }

    public function testClientMultiAddRequest(): void
    {
        $cm = new ClientMulti();
        $req = Request::get('http://localhost:1349/');
        $result = $cm->add_request($req);
        static::assertSame($cm, $result);
    }

    // =========================================================================
    // Request._curlPrep paths not yet covered
    // =========================================================================

    public function testCurlPrepWithBasicAuth(): void
    {
        $req = Request::get('http://localhost:1349/')
            ->withBasicAuth('user', 'pass');
        $prepped = $req->_curlPrep();
        static::assertSame($req, $prepped);
    }

    public function testCurlPrepWithTimeout(): void
    {
        $req = Request::get('http://localhost:1349/')
            ->withTimeout(5.0);
        $prepped = $req->_curlPrep();
        static::assertSame($req, $prepped);
    }

    public function testCurlPrepWithConnectionTimeout(): void
    {
        $req = Request::get('http://localhost:1349/')
            ->withConnectionTimeoutInSeconds(0.5);
        $prepped = $req->_curlPrep();
        static::assertSame($req, $prepped);
    }

    public function testCurlPrepWithFollowRedirects(): void
    {
        $req = Request::get('http://localhost:1349/')
            ->followRedirects(true);
        $prepped = $req->_curlPrep();
        static::assertSame($req, $prepped);
    }

    public function testCurlPrepWithDebug(): void
    {
        // debug is a private field, just test that _curlPrep() doesn't throw for a HEAD request
        $req = Request::head('http://localhost:1349/');
        static::assertSame($req, $req->_curlPrep());
    }

    public function testCurlPrepWithProtocolVersion10(): void
    {
        $req = Request::get('http://localhost:1349/')
            ->withProtocolVersion(Http::HTTP_1_0);
        $prepped = $req->_curlPrep();
        static::assertSame($req, $prepped);
    }

    public function testCurlPrepWithProtocolVersion20(): void
    {
        $req = Request::get('http://localhost:1349/')
            ->withProtocolVersion(Http::HTTP_2_0);
        $prepped = $req->_curlPrep();
        static::assertSame($req, $prepped);
    }

    public function testCurlPrepWithUnknownProtocolVersion(): void
    {
        $req = Request::get('http://localhost:1349/')
            ->withProtocolVersion('99.0');
        $prepped = $req->_curlPrep();
        static::assertSame($req, $prepped);
    }

    public function testCurlPrepPost(): void
    {
        $req = Request::post('http://localhost:1349/', ['key' => 'value'], Mime::JSON);
        $prepped = $req->_curlPrep();
        static::assertSame($req, $prepped);
    }

    public function testCurlPrepHead(): void
    {
        $req = Request::head('http://localhost:1349/');
        $prepped = $req->_curlPrep();
        static::assertSame($req, $prepped);
    }

    public function testCurlPrepWithAdditionalCurlOpts(): void
    {
        $req = Request::get('http://localhost:1349/')
            ->withCurlOption(\CURLOPT_VERBOSE, false);
        $prepped = $req->_curlPrep();
        static::assertSame($req, $prepped);
    }

    public function testCurlPrepWithParams(): void
    {
        $req = Request::get('http://localhost:1349/')
            ->withParams(['a' => '1', 'b' => '2']);
        $prepped = $req->_curlPrep();
        static::assertSame($req, $prepped);
    }

    public function testCurlPrepWithPort(): void
    {
        $req = Request::get('http://localhost:1349/')
            ->withPort(1349);
        $prepped = $req->_curlPrep();
        static::assertSame($req, $prepped);
    }

    public function testCurlPrepWithKeepAlive(): void
    {
        $req = Request::get('http://localhost:1349/')
            ->enableKeepAlive(30);
        $prepped = $req->_curlPrep();
        static::assertSame($req, $prepped);
    }

    public function testCurlPrepWithCacheControl(): void
    {
        $req = Request::get('http://localhost:1349/')
            ->withCacheControl('no-cache');
        $prepped = $req->_curlPrep();
        static::assertSame($req, $prepped);
    }

    public function testCurlPrepWithExpectedType(): void
    {
        $req = Request::get('http://localhost:1349/')
            ->withExpectedType(Mime::JSON);
        $prepped = $req->_curlPrep();
        static::assertSame($req, $prepped);
    }

    public function testCurlPrepWithCustomAcceptHeader(): void
    {
        $req = Request::get('http://localhost:1349/')
            ->withHeader('Accept', 'application/json');
        $prepped = $req->_curlPrep();
        static::assertSame($req, $prepped);
    }

    public function testCurlPrepWithUserAgent(): void
    {
        $req = Request::get('http://localhost:1349/')
            ->withHeader('User-Agent', 'MyTestAgent/1.0');
        $prepped = $req->_curlPrep();
        static::assertSame($req, $prepped);
    }

    public function testCurlPrepWithStrictSSL(): void
    {
        $req = Request::get('http://localhost:1349/')
            ->enableStrictSSL();
        $prepped = $req->_curlPrep();
        static::assertSame($req, $prepped);
    }

    public function testCurlPrepWithNoStrictSSL(): void
    {
        $req = Request::get('http://localhost:1349/')
            ->disableStrictSSL();
        $prepped = $req->_curlPrep();
        static::assertSame($req, $prepped);
    }

    public function testCurlPrepWithSendCallback(): void
    {
        $called = false;
        $req = Request::get('http://localhost:1349/')
            ->beforeSend(static function () use (&$called) {
                $called = true;
            });
        $req->_curlPrep();
        static::assertTrue($called);
    }

    public function testCurlPrepWithDownload(): void
    {
        $tmpFile = \tempnam(\sys_get_temp_dir(), 'req_dl_');
        $req = Request::download('http://localhost:1349/', $tmpFile);
        $prepped = $req->_curlPrep();
        static::assertSame($req, $prepped);
        static::assertFalse($req->isAutoParse());
        @unlink($tmpFile . '.pccdownload');
        @unlink($tmpFile);
    }

    public function testCurlPrepWithProxy(): void
    {
        $req = Request::get('http://localhost:1349/')
            ->withProxy('http://proxy.example.com:3128');
        $prepped = $req->_curlPrep();
        static::assertSame($req, $prepped);
    }

    public function testCurlPrepWithNtlmAuth(): void
    {
        $req = Request::get('http://localhost:1349/')
            ->withNtlmAuth('user', 'pass');
        $prepped = $req->_curlPrep();
        static::assertSame($req, $prepped);
    }

    public function testRequestGetUri(): void
    {
        $req = Request::get('http://example.com/path?query=1#fragment');
        static::assertStringContainsString('example.com', (string) $req->getUri());
    }

    public function testRequestNeverSerializePayload(): void
    {
        $req = Request::post('http://localhost:1349/', 'raw body')
            ->neverSerializePayload();
        $prepped = $req->_curlPrep();
        static::assertSame($req, $prepped);
    }

    public function testRequestDisableAutoParsing(): void
    {
        $req = Request::get('http://localhost:1349/')
            ->disableAutoParsing();
        static::assertFalse($req->isAutoParse());
    }

    public function testRequestClearHelperData(): void
    {
        $req = Request::get('http://localhost:1349/');
        $req->_curlPrep();
        // clearHelperData only clears helperData, not curl
        $req->clearHelperData();
        static::assertNotNull($req->_curl());
    }

    public function testRequestBuildResponse(): void
    {
        $req = Request::get('http://localhost:1349/');
        $req->_curlPrep();
        $curl = $req->_curl();
        static::assertNotNull($curl);

        $response = $req->_buildResponse('hello world', $curl);
        static::assertInstanceOf(Response::class, $response);
    }

    public function testRequestInitMulti(): void
    {
        $req = Request::get('http://localhost:1349/');
        $multiCurl = $req->initMulti(
            static function () {
            },
            static function () {
            }
        );
        static::assertInstanceOf(MultiCurl::class, $multiCurl);
    }

    public function testRequestGetSendCallback(): void
    {
        $cb = static function () {
        };
        $req = Request::get('http://localhost:1349/')->beforeSend($cb);
        static::assertContains($cb, $req->getSendCallback());
    }

    public function testRequestGetParseCallback(): void
    {
        $cb = static function ($body) {
            return $body;
        };
        $req = Request::get('http://localhost:1349/')->withParseCallback($cb);
        static::assertSame($cb, $req->getParseCallback());
        static::assertTrue($req->hasParseCallback());
    }

    public function testRequestWithDigestAuth(): void
    {
        $req = Request::get('http://localhost:1349/')
            ->withDigestAuth('user', 'pass');
        static::assertTrue($req->hasDigestAuth());
    }

    public function testRequestWithProxy(): void
    {
        $req = Request::get('http://localhost:1349/')
            ->withProxy('proxy.example.com', 3128);
        static::assertInstanceOf(Request::class, $req);
    }

    public function testRequestIsJson(): void
    {
        $req = Request::post('http://localhost:1349/', null, Mime::JSON);
        static::assertTrue($req->isJson());
    }

    public function testRequestIsUpload(): void
    {
        $req = Request::post('http://localhost:1349/', null, Mime::UPLOAD);
        static::assertTrue($req->isUpload());
    }

    public function testRequestIsStrictSsl(): void
    {
        $req = Request::get('http://localhost:1349/')->enableStrictSSL();
        static::assertTrue($req->isStrictSSL());
        $req2 = Request::get('http://localhost:1349/')->disableStrictSSL();
        static::assertFalse($req2->isStrictSSL());
    }

    public function testRequestHasConnectionTimeout(): void
    {
        $req = Request::get('http://localhost:1349/')
            ->withConnectionTimeoutInSeconds(1.0);
        static::assertTrue($req->hasConnectionTimeout());
    }

    public function testRequestHasTimeout(): void
    {
        $req = Request::get('http://localhost:1349/')
            ->withTimeout(30.0);
        static::assertTrue($req->hasTimeout());
    }

    public function testRequestGetMethod(): void
    {
        $req = Request::patch('http://localhost:1349/');
        static::assertSame(Http::PATCH, $req->getMethod());
    }
}
