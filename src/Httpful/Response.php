<?php

declare(strict_types=1);

namespace Httpful;

use Httpful\Exception\ResponseException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use voku\helper\UTF8;

class Response implements ResponseInterface
{
    /**
     * @var StreamInterface
     */
    private $body;

    /**
     * @var mixed|null
     */
    private $raw_body;

    /**
     * @var Headers
     */
    private $headers;

    /**
     * @var mixed|null
     */
    private $raw_headers;

    /**
     * @var RequestInterface|null
     */
    private $request;

    /**
     * @var int
     */
    private $code;

    /**
     * @var string
     */
    private $reason;

    /**
     * @var string
     */
    private $content_type = '';

    /**
     * Parent / Generic type (e.g. xml for application/vnd.github.message+xml)
     *
     * @var string
     */
    private $parent_type = '';

    /**
     * @var string
     */
    private $charset = '';

    /**
     * @var array
     */
    private $meta_data;

    /**
     * @var bool
     */
    private $is_mime_vendor_specific = false;

    /**
     * @var bool
     */
    private $is_mime_personal = false;

    /**
     * @param StreamInterface|string|null $body
     * @param array|string|null           $headers
     * @param RequestInterface|null       $request
     * @param array                       $meta_data
     *                                               <p>e.g. [protocol_version] = '1.1'</p>
     */
    public function __construct(
        $body = null,
        $headers = null,
        RequestInterface $request = null,
        array $meta_data = []
    ) {
        $bodyWasStream = $body instanceof StreamInterface;

        if (!($body instanceof StreamInterface)) {
            $this->raw_body = $body;
            $body = Stream::create($body);
        }

        $this->request = $request;
        $this->raw_headers = $headers;
        $this->meta_data = $meta_data;

        if (!isset($this->meta_data['protocol_version'])) {
            $this->meta_data['protocol_version'] = '1.1';
        }

        if (
            \is_string($headers)
            &&
            $headers !== ''
        ) {
            $this->code = $this->_getResponseCodeFromHeaderString($headers);
            $this->reason = Http::reason($this->code);
            $this->headers = Headers::fromString($headers);
        } elseif (
            \is_array($headers)
            &&
            \count($headers) > 0
        ) {
            $this->code = 200;
            $this->reason = Http::reason($this->code);
            $this->headers = new Headers($headers);
        } else {
            $this->code = 200;
            $this->reason = Http::reason($this->code);
            $this->headers = new Headers();
        }

        $this->_interpretHeaders();

        $preserveOriginalBodyStream = $bodyWasStream
            && $this->request === null
            && (
                $this->raw_headers === null
                || $this->raw_headers === ''
                || $this->raw_headers === []
            );

        $bodyParsed = $preserveOriginalBodyStream ? $body : $this->_parse($body);
        $this->body = $bodyParsed instanceof StreamInterface
            ? $bodyParsed
            : Stream::createNotNull($bodyParsed);
        $this->raw_body = $bodyParsed;
    }

    /**
     * @return void
     */
    public function __clone()
    {
        $this->headers = clone $this->headers;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        if (
            $this->body->getSize() > 0
            &&
            !(
                $this->raw_body
                &&
                UTF8::is_serialized((string) $this->body)
            )
        ) {
            return (string) $this->body;
        }

        if (\is_string($this->raw_body)) {
            return (string) $this->raw_body;
        }

        return (string) \json_encode($this->raw_body);
    }

    /**
     * @param string $headers
     *
     * @throws ResponseException if we are unable to parse response code from HTTP response
     *
     * @return int
     *
     * @internal
     */
    public function _getResponseCodeFromHeaderString($headers): int
    {
        // If there was a redirect, we will get headers from one then one request,
        // but will are only interested in the last request.
        $headersTmp = \explode("\r\n\r\n", $headers);
        $headersTmpCount = \count($headersTmp);
        if ($headersTmpCount >= 2) {
            $headers = $headersTmp[$headersTmpCount - 2];
        }

        $end = \strpos($headers, "\r\n");
        if ($end === false) {
            $end = \strlen($headers);
        }

        $parts = \explode(' ', \substr($headers, 0, $end));

        if (
            \count($parts) < 2
            ||
            !\is_numeric($parts[1])
        ) {
            throw new ResponseException('Unable to parse response code from HTTP response due to malformed response: "' . \print_r($headers, true) . '"');
        }

        return (int) $parts[1];
    }

    /**
     * @return StreamInterface
     */
    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    /**
     * Retrieves a message header value by the given case-insensitive name.
     *
     * This method returns an array of all the header values of the given
     * case-insensitive header name.
     *
     * If the header does not appear in the message, this method MUST return an
     * empty array.
     *
     * @param string $name case-insensitive header field name
     *
     * @return string[] An array of string values as provided for the given
     *                  header. If the header does not appear in the message, this method MUST
     *                  return an empty array.
     */
    public function getHeader($name): array
    {
        if ($this->headers->offsetExists($name)) {
            $value = $this->headers->offsetGet($name);

            if (!\is_array($value)) {
                return [\trim($value, " \t")];
            }

            foreach ($value as $keyInner => $valueInner) {
                $value[$keyInner] = \trim($valueInner, " \t");
            }

            return $value;
        }

        return [];
    }

    /**
     * Retrieves a comma-separated string of the values for a single header.
     *
     * This method returns all of the header values of the given
     * case-insensitive header name as a string concatenated together using
     * a comma.
     *
     * NOTE: Not all header values may be appropriately represented using
     * comma concatenation. For such headers, use getHeader() instead
     * and supply your own delimiter when concatenating.
     *
     * If the header does not appear in the message, this method MUST return
     * an empty string.
     *
     * @param string $name case-insensitive header field name
     *
     * @return string A string of values as provided for the given header
     *                concatenated together using a comma. If the header does not appear in
     *                the message, this method MUST return an empty string.
     */
    public function getHeaderLine($name): string
    {
        return \implode(', ', $this->getHeader($name));
    }

    /**
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers->toArray();
    }

    /**
     * Retrieves the HTTP protocol version as a string.
     *
     * The string MUST contain only the HTTP version number (e.g., "1.1", "1.0").
     *
     * @return string HTTP protocol version
     */
    public function getProtocolVersion(): string
    {
        if (isset($this->meta_data['protocol_version'])) {
            return (string) $this->meta_data['protocol_version'];
        }

        return '1.1';
    }

    /**
     * Gets the response reason phrase associated with the status code.
     *
     * Because a reason phrase is not a required element in a response
     * status line, the reason phrase value MAY be null. Implementations MAY
     * choose to return the default RFC 7231 recommended reason phrase (or those
     * listed in the IANA HTTP Status Code Registry) for the response's
     * status code.
     *
     * @see http://tools.ietf.org/html/rfc7231#section-6
     * @see http://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml
     *
     * @return string reason phrase; must return an empty string if none present
     */
    public function getReasonPhrase(): string
    {
        return $this->reason;
    }

    /**
     * Gets the response status code.
     *
     * The status code is a 3-digit integer result code of the server's attempt
     * to understand and satisfy the request.
     *
     * @return int status code
     */
    public function getStatusCode(): int
    {
        return $this->code;
    }

    /**
     * Checks if a header exists by the given case-insensitive name.
     *
     * @param string $name case-insensitive header field name
     *
     * @return bool Returns true if any header names match the given header
     *              name using a case-insensitive string comparison. Returns false if
     *              no matching header name is found in the message.
     */
    public function hasHeader($name): bool
    {
        return $this->headers->offsetExists($name);
    }

    /**
     * Return an instance with the specified header appended with the given value.
     *
     * Existing values for the specified header will be maintained. The new
     * value(s) will be appended to the existing list. If the header did not
     * exist previously, it will be added.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new header and/or value.
     *
     * @param string          $name  case-insensitive header field name to add
     * @param string|string[] $value header value(s)
     *
     * @throws \InvalidArgumentException for invalid header names or values
     *
     * @return static
     */
    public function withAddedHeader($name, $value): \Psr\Http\Message\MessageInterface
    {
        $new = clone $this;

        if (!\is_array($value)) {
            $value = [$value];
        }

        if ($new->headers->offsetExists($name)) {
            $new->headers->forceSet($name, \array_merge_recursive($new->headers->offsetGet($name), $value));
        } else {
            $new->headers->forceSet($name, $value);
        }

        return $new;
    }

    /**
     * Return an instance with the specified message body.
     *
     * The body MUST be a StreamInterface object.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return a new instance that has the
     * new body stream.
     *
     * @param StreamInterface $body body
     *
     * @throws \InvalidArgumentException when the body is not valid
     *
     * @return static
     */
    public function withBody(StreamInterface $body): \Psr\Http\Message\MessageInterface
    {
        $new = clone $this;

        $new->body = $body;

        return $new;
    }

    /**
     * Return an instance with the provided value replacing the specified header.
     *
     * While header names are case-insensitive, the casing of the header will
     * be preserved by this function, and returned from getHeaders().
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new and/or updated header and value.
     *
     * @param string          $name  case-insensitive header field name
     * @param string|string[] $value header value(s)
     *
     * @throws \InvalidArgumentException for invalid header names or values
     *
     * @return static
     */
    public function withHeader($name, $value): \Psr\Http\Message\MessageInterface
    {
        $new = clone $this;

        if (!\is_array($value)) {
            $value = [$value];
        }

        $new->headers->forceSet($name, $value);

        return $new;
    }

    /**
     * Return an instance with the specified HTTP protocol version.
     *
     * The version string MUST contain only the HTTP version number (e.g.,
     * "1.1", "1.0").
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new protocol version.
     *
     * @param string $version HTTP protocol version
     *
     * @return static
     */
    public function withProtocolVersion($version): \Psr\Http\Message\MessageInterface
    {
        $new = clone $this;

        $new->meta_data['protocol_version'] = $version;

        return $new;
    }

    /**
     * Return an instance with the specified status code and, optionally, reason phrase.
     *
     * If no reason phrase is specified, implementations MAY choose to default
     * to the RFC 7231 or IANA recommended reason phrase for the response's
     * status code.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * updated status and reason phrase.
     *
     * @see http://tools.ietf.org/html/rfc7231#section-6
     * @see http://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml
     *
     * @param int    $code         the 3-digit integer result code to set
     * @param string $reasonPhrase the reason phrase to use with the
     *                             provided status code; if none is provided, implementations MAY
     *                             use the defaults as suggested in the HTTP specification
     *
     * @throws \InvalidArgumentException for invalid status code arguments
     *
     * @return static
     */
    public function withStatus($code, $reasonPhrase = null): ResponseInterface
    {
        $new = clone $this;

        $new->code = (int) $code;

        if (Http::responseCodeExists($new->code)) {
            $new->reason = Http::reason($new->code);
        } else {
            $new->reason = '';
        }

        if ($reasonPhrase !== null) {
            $new->reason = $reasonPhrase;
        }

        return $new;
    }

    /**
     * Return an instance without the specified header.
     *
     * Header resolution MUST be done without case-sensitivity.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that removes
     * the named header.
     *
     * @param string $name case-insensitive header field name to remove
     *
     * @return static
     */
    public function withoutHeader($name): \Psr\Http\Message\MessageInterface
    {
        $new = clone $this;

        $new->headers->forceUnset($name);

        return $new;
    }

    /**
     * @return string
     */
    public function getCharset(): string
    {
        return $this->charset;
    }

    /**
     * @return string
     */
    public function getContentType(): string
    {
        return $this->content_type;
    }

    /**
     * @return Headers
     */
    public function getHeadersObject(): Headers
    {
        return $this->headers;
    }

    /**
     * @return array
     */
    public function getMetaData(): array
    {
        return $this->meta_data;
    }

    /**
     * @return string
     */
    public function getParentType(): string
    {
        return $this->parent_type;
    }

    /**
     * @return mixed
     */
    public function getRawBody()
    {
        return $this->raw_body;
    }

    /**
     * @return string
     */
    public function getRawHeaders(): string
    {
        return $this->raw_headers;
    }

    public function hasBody(): bool
    {
        return $this->body->getSize()  > 0;
    }

    /**
     * Status Code Definitions.
     *
     * Informational 1xx
     * Successful    2xx
     * Redirection   3xx
     * Client Error  4xx
     * Server Error  5xx
     *
     * http://pretty-rfc.herokuapp.com/RFC2616#status.codes
     *
     * @return bool Did we receive a 4xx or 5xx?
     */
    public function hasErrors(): bool
    {
        return $this->code >= 400;
    }

    /**
     * @return bool Did we receive a 1xx Informational response?
     */
    public function isInformational(): bool
    {
        return $this->code >= 100 && $this->code < 200;
    }

    /**
     * @return bool Did we receive a 2xx Successful response?
     */
    public function isSuccess(): bool
    {
        return $this->code >= 200 && $this->code < 300;
    }

    /**
     * @return bool Did we receive a 3xx Redirection response?
     */
    public function isRedirect(): bool
    {
        return $this->code >= 300 && $this->code < 400;
    }

    /**
     * @return bool Did we receive a 4xx Client Error response?
     */
    public function isClientError(): bool
    {
        return $this->code >= 400 && $this->code < 500;
    }

    /**
     * @return bool Did we receive a 5xx Server Error response?
     */
    public function isServerError(): bool
    {
        return $this->code >= 500 && $this->code < 600;
    }

    /**
     * Returns a human-readable hint / explanation for the current HTTP status code.
     *
     * Useful when displaying or logging error information without having to
     * hard-code status-code ranges in calling code.
     *
     * @return string
     */
    public function getErrorMessage(): string
    {
        if ($this->isSuccess()) {
            return 'Request was successful (' . $this->code . ' ' . $this->reason . ').';
        }

        if ($this->isInformational()) {
            return 'Informational response (' . $this->code . ' ' . $this->reason . '): the server acknowledged the request.';
        }

        if ($this->isRedirect()) {
            return 'Redirect response (' . $this->code . ' ' . $this->reason . '): the resource has moved. Check the Location header.';
        }

        if ($this->isClientError()) {
            $hints = [
                400 => 'Bad Request: the server could not understand the request due to invalid syntax.',
                401 => 'Unauthorized: authentication is required and has failed or not been provided.',
                403 => 'Forbidden: you do not have permission to access this resource.',
                404 => 'Not Found: the requested resource could not be found on the server.',
                405 => 'Method Not Allowed: the HTTP method used is not supported for this endpoint.',
                408 => 'Request Timeout: the server timed out waiting for the request.',
                409 => 'Conflict: the request conflicts with the current state of the resource.',
                410 => 'Gone: the resource has been permanently removed.',
                422 => 'Unprocessable Entity: the request was well-formed but contains semantic errors.',
                429 => 'Too Many Requests: you have exceeded the rate limit. Try again later.',
            ];

            $hint = $hints[$this->code] ?? 'Client Error: the request could not be fulfilled due to a client-side problem. Check your request parameters, headers and authentication.';

            return $hint . ' (' . $this->code . ' ' . $this->reason . ')';
        }

        if ($this->isServerError()) {
            $hints = [
                500 => 'Internal Server Error: the server encountered an unexpected error. This is a server-side problem.',
                501 => 'Not Implemented: the server does not support the functionality required to fulfil the request.',
                502 => 'Bad Gateway: the server received an invalid response from an upstream server.',
                503 => 'Service Unavailable: the server is temporarily unable to handle the request (overloaded or under maintenance).',
                504 => 'Gateway Timeout: the upstream server did not respond in time.',
            ];

            $hint = $hints[$this->code] ?? 'Server Error: the server failed to fulfil the request. This is a server-side problem and is not caused by your request.';

            return $hint . ' (' . $this->code . ' ' . $this->reason . ')';
        }

        return 'Unknown response status (' . $this->code . ' ' . $this->reason . ').';
    }

    /**
     * Returns a human-readable debug summary that includes the original
     * request (method, full URL with query params, request headers, request
     * body) as well as the response (status, hint, response headers, response
     * body).  Useful for logging, debugging, or building descriptive exception
     * messages.
     *
     * @return string
     */
    public function debugInfo(): string
    {
        $lines = [];

        // ---- Request section (available when the response was produced by a Request) ----
        if ($this->request !== null) {
            $lines[] = '--- Request ---';
            $lines[] = $this->request->getMethod() . ' ' . (string) $this->request->getUri();

            foreach ($this->request->getHeaders() as $name => $values) {
                $lines[] = $name . ': ' . \implode(', ', (array) $values);
            }

            $requestBody = (string) $this->request->getBody();
            if ($requestBody !== '') {
                $lines[] = '';
                $lines[] = $requestBody;
            }

            $lines[] = '';
        }

        // ---- Response section ----
        $lines[] = '=== Response Debug Info ===';
        $lines[] = 'Status : ' . $this->code . ' ' . $this->reason;
        $lines[] = 'Hint   : ' . $this->getErrorMessage();
        $lines[] = '';
        $lines[] = '--- Response Headers ---';

        foreach ($this->headers->toArray() as $name => $values) {
            $lines[] = $name . ': ' . \implode(', ', (array) $values);
        }

        $lines[] = '';
        $lines[] = '--- Response Body ---';
        $lines[] = (string) $this->body;

        return \implode("\n", $lines);
    }

    /**
     * @return bool
     */
    public function isMimePersonal(): bool
    {
        return $this->is_mime_personal;
    }

    /**
     * @return bool
     */
    public function isMimeVendorSpecific(): bool
    {
        return $this->is_mime_vendor_specific;
    }

    /**
     * @param string[] $header
     *
     * @return static
     */
    public function withHeaders(array $header)
    {
        $new = clone $this;

        foreach ($header as $name => $value) {
            $new = $new->withHeader($name, $value);
        }

        return $new;
    }

    /**
     * After we've parse the headers, let's clean things
     * up a bit and treat some headers specially
     *
     * @return void
     */
    private function _interpretHeaders()
    {
        // Parse the Content-Type and charset
        $content_type = $this->headers['Content-Type'] ?? [];
        foreach ($content_type as $content_type_inner) {
            $content_type = \array_merge(\explode(';', $content_type_inner));
        }

        $this->content_type = $content_type[0] ?? '';
        if (
            \count($content_type) === 2
            &&
            \strpos($content_type[1], '=') !== false
        ) {
            /** @noinspection PhpUnusedLocalVariableInspection */
            list($nill, $this->charset) = \explode('=', $content_type[1]);
        }

        // fallback
        if (!$this->charset) {
            $this->charset = 'utf-8';
        }

        // check for vendor & personal type
        if (\strpos($this->content_type, '/') !== false) {
            /** @noinspection PhpUnusedLocalVariableInspection */
            list($type, $sub_type) = \explode('/', $this->content_type);
            $this->is_mime_vendor_specific = \strpos($sub_type, 'vnd.') === 0;
            $this->is_mime_personal = \strpos($sub_type, 'prs.') === 0;
        }

        $this->parent_type = $this->content_type;
        if (\strpos($this->content_type, '+') !== false) {
            /** @noinspection PhpUnusedLocalVariableInspection */
            list($vendor, $this->parent_type) = \explode('+', $this->content_type, 2);
            $this->parent_type = Mime::getFullMime($this->parent_type);
        }
    }

    /**
     * Parse the response into a clean data structure
     * (most often an associative array) based on the expected
     * Mime type.
     *
     * @param StreamInterface|null $body Http response body
     *
     * @return mixed the response parse accordingly
     */
    private function _parse($body)
    {
        // If the user decided to forgo the automatic smart parsing, short circuit.
        if (
            $this->request instanceof Request
            &&
            !$this->request->isAutoParse()
        ) {
            return $body;
        }

        // If provided, use custom parsing callback.
        if (
            $this->request instanceof Request
            &&
            $this->request->hasParseCallback()
        ) {
            return \call_user_func($this->request->getParseCallback(), $body);
        }

        // Decide how to parse the body of the response in the following order:
        //
        //  1. If provided, use the mime type specifically set as part of the `Request`
        //  2. If a MimeHandler is registered for the content type, use it
        //  3. If provided, use the "parent type" of the mime type from the response
        //  4. Default to the content-type provided in the response
        if ($this->request instanceof Request) {
            $parse_with = $this->request->getExpectedType();
        }

        if (empty($parse_with)) {
            if (Setup::hasParserRegistered($this->content_type)) {
                $parse_with = $this->content_type;
            } else {
                $parse_with = $this->parent_type;
            }
        }

        return Setup::setupGlobalMimeType($parse_with)->parse((string) $body);
    }
}
