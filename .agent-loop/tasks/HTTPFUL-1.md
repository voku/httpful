# HTTPFUL-1: Support the QUERY HTTP method (RFC 10008)

## Goal

Make QUERY a first-class HTTP method in httpful, the same way PATCH and PUT are.

## Background

RFC 10008 ("The HTTP QUERY Method", Standards Track, June 2026) defines QUERY as a method
that carries a request body like POST, but is **safe** and **idempotent** like GET. That
combination is new: httpful currently has no method that is both safe and body-carrying, so
QUERY cannot be expressed by reusing an existing constant.

RFC 10008 properties that matter for a client library:

- Safe with regard to the target resource.
- Idempotent; a request may be retried or repeated.
- The response is cacheable.
- The request has content, and the server MUST fail the request when `Content-Type` is
  missing or inconsistent with that content - so the helper must always send a MIME type.
- `Accept-Query` is a **response** header by which a resource advertises the query formats it
  accepts.

## Scope

- `src/Httpful/Http.php` - `QUERY` constant, plus `allMethods()`, `safeMethods()` and
  `idempotentMethods()`.
- `src/Httpful/Request.php` - `Request::query()` body-carrying factory.
- `src/Httpful/Client.php` - `Client::query()` / `Client::query_request()`.
- `src/Httpful/ClientMulti.php` - `add_query()`.
- `tests/Httpful/` - coverage for the classification and for the helpers.

## Out of scope

- Parsing or emitting `Accept-Query` (a response-side concern, no consumer in this task).
- Any cache layer honouring QUERY cacheability; httpful has no response cache.

## Verification

- `vendor/bin/phpunit`
- `vendor/bin/phpstan analyse`
