[![Build Status](https://github.com/voku/httpful/actions/workflows/ci.yml/badge.svg?branch=master)](https://github.com/voku/httpful/actions)
[![codecov.io](https://codecov.io/github/voku/httpful/coverage.svg?branch=master)](https://codecov.io/github/voku/httpful?branch=master)
[![Codacy Badge](https://api.codacy.com/project/badge/Grade/5882e37a6cd24f6c9d1cf70a08064146)](https://www.codacy.com/app/voku/httpful)
[![Latest Stable Version](https://poser.pugx.org/voku/httpful/v/stable)](https://packagist.org/packages/voku/httpful) 
[![Total Downloads](https://poser.pugx.org/voku/httpful/downloads)](https://packagist.org/packages/voku/httpful)
[![License](https://poser.pugx.org/voku/httpful/license)](https://packagist.org/packages/voku/httpful)
[![Donate to this project using Paypal](https://img.shields.io/badge/paypal-donate-yellow.svg)](https://www.paypal.me/moelleken)
[![Donate to this project using Patreon](https://img.shields.io/badge/patreon-donate-yellow.svg)](https://www.patreon.com/voku)

# 📯 Httpful

Forked some years ago from [nategood/httpful](https://github.com/nategood/httpful) + added support for parallel request and implemented many PSR Interfaces: A Chainable, REST Friendly Wrapper for cURL with many "PSR-HTTP" implemented interfaces. 

Features

 - Readable HTTP Method Support (GET, PUT, POST, DELETE, HEAD, PATCH and OPTIONS)
 - Custom Headers
 - Automatic "Smart" Parsing
 - Automatic Payload Serialization
 - Basic Auth
 - Bearer Token Auth
  - Client Side Certificate Auth (SSL)
 - Retry Configuration (count, delay, max time, all-errors, connection-refused)
 - Advanced TLS Configuration (CA bundle / path, pinned public key, TLS version)
 - Cookie Persistence (cookie file / cookie jar)
 - Modern HTTP Version Helpers (HTTP/2 prior knowledge, HTTP/3, HTTP/3 only)
 - Alt-Svc / HSTS Cache Helpers
 - Proxy / Routing Helpers (no-proxy, proxy tunnel, resolve, connect-to)
  - Request "Download"
  - Request "Templates"
  - Parallel Request (via curl_multi)
 - Transfer Metadata Helpers
  - PSR-3: Logger Interface
  - PSR-7: HTTP Message Interface
  - PSR-17: HTTP Factory Interface
  - PSR-18: HTTP Client Interface

# Examples

```php
<?php

// Make a request to the GitHub API.

$uri = 'https://api.github.com/users/voku';
$response = \Httpful\Client::get($uri, null, \Httpful\Mime::JSON);

echo $response->getBody()->name . ' joined GitHub on ' . date('M jS Y', strtotime($response->getBody()->created_at)) . "\n";
```

```php
<?php

// Make a request to the GitHub API with a custom
// header of "X-Foo-Header: Just as a demo".

$uri = 'https://api.github.com/users/voku';
$response = \Httpful\Client::get_request($uri)->withAddedHeader('X-Foo-Header', 'Just as a demo')
                                              ->expectsJson()
                                              ->send();

$result = $response->getRawBody();

echo $result['name'] . ' joined GitHub on ' . \date('M jS Y', \strtotime($result['created_at'])) . "\n";
```

```php
<?php

// BasicAuth example with MultiCurl for async requests.

/** @var \Httpful\Response[] $results */
$results = [];
$multi = new \Httpful\ClientMulti(
    static function (\Httpful\Response $response, \Httpful\Request $request) use (&$results) {
        $results[] = $response;
    }
);

$request = (new \Httpful\Request(\Httpful\Http::GET))
    ->withUriFromString('https://postman-echo.com/basic-auth')
    ->withBasicAuth('postman', 'password');

$multi->add_request($request);
// $multi->add_request(...); // add more calls here

$multi->start();

// DEBUG
//print_r($results);
```

```php
<?php

$response = \Httpful\Request::get('https://api.example.com/items')
    ->withBearerToken('secret-token')
    ->withRetry(3)
    ->withRetryDelay(1)
    ->withRetryMaxTime(10)
    ->withCookieJar('/tmp/httpful.cookies')
    ->withCaBundle('/etc/ssl/certs/ca-bundle.crt')
    ->withHttp2PriorKnowledge()
    ->send();

echo $response->getEffectiveUrl() . "\n";
echo $response->getTransferHttpVersion() . "\n";
echo $response->getTotalTime() . "\n";
```

# Installation

```shell
composer require voku/httpful
```

Requires PHP 8.0+.

## Handlers

We can override the default parser configuration options be registering
a parser with different configuration options for a particular mime type

Example: setting a namespace for the XMLHandler parser
```php
$conf = ['namespace' => 'http://example.com'];
\Httpful\Setup::registerMimeHandler(\Httpful\Mime::XML, new \Httpful\Handlers\XmlMimeHandler($conf));
```

---

Handlers are simple classes that are used to parse response bodies and serialize request payloads.  All Handlers must implement the `MimeHandlerInterface` interface and implement two methods: `serialize($payload)` and `parse($response)`.  Let's build a very basic Handler to register for the `text/csv` mime type.

```php
<?php

class SimpleCsvMimeHandler extends \Httpful\Handlers\DefaultMimeHandler
{
    /**
     * Takes a response body, and turns it into
     * a two dimensional array.
     *
     * @param string $body
     *
     * @return array
     */
    public function parse($body)
    {
        return \str_getcsv($body);
    }

    /**
     * Takes a two dimensional array and turns it
     * into a serialized string to include as the
     * body of a request
     *
     * @param mixed $payload
     *
     * @return string
     */
    public function serialize($payload)
    {
        // init
        $serialized = '';

        foreach ($payload as $line) {
            $serialized .= '"' . \implode('","', $line) . '"' . "\n";
        }

        return $serialized;
    }
}

\Httpful\Setup::registerMimeHandler(\Httpful\Mime::CSV, new SimpleCsvMimeHandler());

```

Finally, you must register this handler for a particular mime type.

```
\Httpful\Setup::register(Mime::CSV, new SimpleCsvHandler());
```

After this registering the handler in your source code, by default, any responses with a mime type of text/csv should be parsed by this handler.
