<?php

declare(strict_types=1);

namespace Httpful;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

class ServerRequest extends Request implements ServerRequestInterface
{
    /**
     * @var array<string, mixed>
     */
    private $attributes = [];

    /**
     * @var array<string, string>
     */
    private $cookieParams = [];

    /**
     * @var array<string, mixed>|object|null
     */
    private $parsedBody;

    /**
     * @var array<string, mixed>
     */
    private $queryParams = [];

    /**
     * @var array<string, mixed>
     */
    private $serverParams;

    /**
     * @var UploadedFileInterface[]
     */
    private $uploadedFiles = [];

    /**
     * @param string|null         $method       Http Method
     * @param string|null         $mime         Mime Type to Use
     * @param Request|null        $template     "Request"-template object
     * @param array<string,mixed> $serverParams Typically the $_SERVER (superglobal)
     */
    public function __construct(
        ?string $method = null,
        ?string $mime = null,
        ?Request $template = null,
        array $serverParams = []
    ) {
        $this->serverParams = $serverParams;

        parent::__construct($method, $mime, $template);
    }

    /**
     * @param string $name
     * @param mixed  $default
     *
     * @return mixed|null
     */
    public function getAttribute($name, $default = null)
    {
        if (\array_key_exists($name, $this->attributes) === false) {
            return $default;
        }

        return $this->attributes[$name];
    }

    /**
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @return array<string, string>
     */
    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    /**
     * @return array<string, mixed>|object|null
     */
    public function getParsedBody()
    {
        return $this->parsedBody;
    }

    /**
     * @return array<string, mixed>
     */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * @return array<string, mixed>
     */
    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    /**
     * @return array<string, UploadedFileInterface>
     */
    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @return static
     */
    public function withAttribute($name, $value): self
    {
        $new = clone $this;
        $new->attributes[$name] = $value;

        return $new;
    }

    /**
     * @param array<string, string> $cookies
     *
     * @return ServerRequest|ServerRequestInterface
     */
    public function withCookieParams(array $cookies): ServerRequestInterface
    {
        $new = clone $this;
        $new->cookieParams = $cookies;

        return $new;
    }

    /**
     * @param mixed $data
     *
     * @return ServerRequest|ServerRequestInterface
     */
    public function withParsedBody($data): ServerRequestInterface
    {
        if (
            !\is_array($data)
            &&
            !\is_object($data)
            &&
            $data !== null
        ) {
            throw new \InvalidArgumentException('First parameter to withParsedBody MUST be object, array or null');
        }

        $new = clone $this;
        $new->parsedBody = $data;

        return $new;
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return ServerRequestInterface|static
     */
    public function withQueryParams(array $query): ServerRequestInterface
    {
        $new = clone $this;
        $new->queryParams = $query;

        return $new;
    }

    /**
     * @param array<string, UploadedFileInterface> $uploadedFiles
     *
     * @return ServerRequestInterface|static
     */
    public function withUploadedFiles(array $uploadedFiles): ServerRequestInterface
    {
        $new = clone $this;
        $new->uploadedFiles = $uploadedFiles;

        return $new;
    }

    /**
     * @param string $name
     *
     * @return static
     */
    public function withoutAttribute($name): self
    {
        if (\array_key_exists($name, $this->attributes) === false) {
            return $this;
        }

        $new = clone $this;
        unset($new->attributes[$name]);

        return $new;
    }
}
