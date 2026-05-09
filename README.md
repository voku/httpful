[![Build Status](https://github.com/voku/httpful/actions/workflows/ci.yml/badge.svg?branch=master)](https://github.com/voku/httpful/actions/workflows/ci.yml)
[![codecov.io](https://codecov.io/github/voku/httpful/coverage.svg?branch=master)](https://codecov.io/github/voku/httpful?branch=master)
[![Latest Stable Version](https://poser.pugx.org/voku/httpful/v/stable)](https://packagist.org/packages/voku/httpful)
[![Total Downloads](https://poser.pugx.org/voku/httpful/downloads)](https://packagist.org/packages/voku/httpful)
[![License](https://poser.pugx.org/voku/httpful/license)](https://packagist.org/packages/voku/httpful)

# Httpful

Httpful is a fluent PHP HTTP client built on top of cURL. This fork keeps the original chainable API while adding modern PSR interfaces, better transport controls, async helpers, and curl-multi support for high-volume workloads.

## Why Httpful

- Clear request builders for `GET`, `POST`, `PUT`, `PATCH`, `DELETE`, `HEAD`, and `OPTIONS`
- Automatic payload serialization and response parsing for JSON, XML, HTML, forms, CSV, and plain text
- PSR-3, PSR-7, PSR-17, and PSR-18 support
- Retry, timeout, TLS, proxy, cookie jar, redirect, and download helpers
- Async request promises and parallel execution with curl-multi
- Extensible mime handler registration for custom content types

## Installation

```bash
composer require voku/httpful
```

Requirements:

- PHP 8.0+
- `ext-curl`
- `ext-dom`
- `ext-fileinfo`
- `ext-json`
- `ext-simplexml`
- `ext-xmlwriter`

## Quick start

### Simple JSON request

```php
<?php

declare(strict_types=1);

use Httpful\Client;

$response = Client::get('https://api.github.com/users/voku', null, \Httpful\Mime::JSON);

$data = $response->getRawBody();

echo $data['login'] . PHP_EOL;
echo $response->getCode() . PHP_EOL;
```

### Fluent request builder

```php
<?php

declare(strict_types=1);

use Httpful\Request;

$response = Request::get('https://api.github.com/repos/voku/httpful')
    ->expectsJson()
    ->withAddedHeader('Accept', 'application/vnd.github+json')
    ->withAddedHeader('User-Agent', 'httpful-docs-example')
    ->followRedirects()
    ->send();

$repository = $response->getRawBody();

echo $repository['full_name'] . PHP_EOL;
```

## Common workflows

### Send JSON payloads

```php
<?php

$response = \Httpful\Request::post('https://api.example.com/items')
    ->sendsJson()
    ->expectsJson()
    ->body(['name' => 'demo', 'status' => 'active'])
    ->send();
```

### Authentication

```php
<?php

$response = \Httpful\Request::get('https://api.example.com/private')
    ->withBasicAuth('username', 'password')
    ->send();

$bearerResponse = \Httpful\Request::get('https://api.example.com/private')
    ->withBearerToken('secret-token')
    ->send();
```

### Transport controls

```php
<?php

$response = \Httpful\Request::get('https://api.example.com/items')
    ->expectsJson()
    ->withRetry(3)
    ->withRetryDelay(1)
    ->withRetryMaxTime(10)
    ->withCookieJar('/tmp/httpful.cookies')
    ->withCaBundle('/etc/ssl/certs/ca-bundle.crt')
    ->withHttp2PriorKnowledge()
    ->withTimeout(15)
    ->send();
```

### File downloads

```php
<?php

$response = \Httpful\Client::download(
    'https://example.com/archive.zip',
    '/tmp/archive.zip',
    30
);

echo $response->getCode() . PHP_EOL;
```

## Async and parallel requests

### Promise-based async request

```php
<?php

$promise = \Httpful\Request::get('https://api.example.com/items')
    ->authenticateWithBearerToken('secret-token')
    ->sendAsync();

$response = $promise->wait();

echo $response->getCode() . PHP_EOL;
```

### Parallel requests with `ClientMulti`

```php
<?php

$results = [];
$multi = new \Httpful\ClientMulti(
    static function (\Httpful\Response $response, \Httpful\Request $request) use (&$results): void {
        $results[] = [
            'uri' => (string) $request->getUri(),
            'status' => $response->getCode(),
        ];
    }
);

$multi
    ->add_get('https://postman-echo.com/get?name=httpful')
    ->add_get('https://postman-echo.com/get?name=parallel');

$multi->start();

var_dump($results);
```

## Response handling

`Httpful\Response` implements `Psr\Http\Message\ResponseInterface` and keeps parsed and raw accessors available:

- `getCode()` for the HTTP status code
- `getBody()` for the PSR-7 stream
- `getRawBody()` for the parsed body value
- `getHeaders()` and `hasHeader()` for response metadata
- `getMetaData()` for curl transfer details such as protocol version and timing data

## PSR support

Httpful ships with interfaces and helpers that make it usable in PSR-based applications:

- `Httpful\Client` implements `Psr\Http\Client\ClientInterface`
- `Httpful\ClientPromise` implements `Http\Client\HttpAsyncClient`
- `Httpful\Request` implements `Psr\Http\Message\RequestInterface`
- `Httpful\Response` implements `Psr\Http\Message\ResponseInterface`
- Request and response factories are available for PSR-17 style integrations

## Custom mime handlers

Register a custom parser or serializer when you need special handling for a content type.

```php
<?php

use Httpful\Handlers\DefaultMimeHandler;
use Httpful\Mime;
use Httpful\Setup;

final class SimpleCsvMimeHandler extends DefaultMimeHandler
{
    public function parse($body)
    {
        return str_getcsv($body);
    }

    public function serialize($payload)
    {
        $serialized = '';

        foreach ($payload as $line) {
            $serialized .= '"' . implode('","', $line) . '"' . "\n";
        }

        return $serialized;
    }
}

Setup::registerMimeHandler(Mime::CSV, new SimpleCsvMimeHandler());
```

Use `Setup::registerGlobalMimeHandler()` to override the default handler and `Setup::registerGlobalErrorHandler()` to install a callable or PSR-3 logger for transport failures.

## Key Files Detector helper prompt

Use this prompt when you want an assistant to find the most relevant implementation files quickly:

```text
You are reviewing the voku/httpful repository.

Start with these key files and explain why each one matters before exploring anything else:
- README.md
- composer.json
- .github/workflows/ci.yml
- src/Httpful/Client.php
- src/Httpful/Request.php
- src/Httpful/Response.php
- src/Httpful/Factory.php
- src/Httpful/ClientMulti.php
- src/Httpful/Setup.php
- src/Httpful/Mime.php
- tests/Httpful/
- examples/

Then identify any additional files that are critical for the task at hand, grouped by API surface, transport internals, test coverage, and release automation.
Return the result as a prioritized checklist with short explanations.
```

## Repository layout

- `/src/Httpful` — library source code
- `/tests/Httpful` — PHPUnit coverage for the public API and curl integrations
- `/examples` — runnable usage samples
- `/.github/workflows/ci.yml` — continuous integration workflow
- `/README.md` — primary user-facing documentation

## Development

Install dependencies and run the same baseline checks used during development:

```bash
composer validate --strict
composer dump-autoload -o
composer audit
php vendor/bin/phpunit -c phpunit.xml.dist
php vendor/bin/phpstan analyse
```

When working on the documentation site locally:

```bash
npm install
npm run dev
npm run build
```

## License

MIT. See [`LICENSE.txt`](LICENSE.txt).
