<?php

declare(strict_types=1);

namespace Httpful\tests;

use Httpful\Curl\Curl;
use Httpful\Request;
use PHPUnit\Framework\TestCase;

/**
 * Test cURL options through the real local HTTP fixture, not implementation details.
 *
 * @internal
 */
final class CurlOptionIntegrationTest extends TestCase
{
    private static function url(string $query = ''): string
    {
        return 'http://' . \TEST_SERVER . '/cookie-echo.php' . $query;
    }

    public function testCookieStringIsSentOverHttp(): void
    {
        $curl = new Curl(self::url());

        try {
            $curl->setCookieString('token=from-string');
            self::assertSame('token=from-string', $curl->exec());
        } finally {
            $curl->close();
        }
    }

    public function testNetscapeCookieFileIsReadBeforeHttpRequest(): void
    {
        $filename = \tempnam(\sys_get_temp_dir(), 'httpful-cookies-');
        self::assertIsString($filename);

        try {
            self::assertNotFalse(\file_put_contents(
                $filename,
                "# Netscape HTTP Cookie File\nlocalhost\tFALSE\t/\tFALSE\t0\ttoken\tfrom-file\n"
            ));

            $curl = new Curl(self::url());

            try {
                $curl->setCookieFile($filename);
                self::assertStringContainsString('token=from-file', (string) $curl->exec());
            } finally {
                $curl->close();
            }
        } finally {
            \unlink($filename);
        }
    }

    public function testCookieJarPersistsServerCookieOnClose(): void
    {
        $filename = \tempnam(\sys_get_temp_dir(), 'httpful-cookiejar-');
        self::assertIsString($filename);
        \unlink($filename);

        try {
            $curl = new Curl(self::url('?set_cookie=1'));

            try {
                $curl->setCookieJar($filename);
                self::assertNotFalse($curl->exec());
            } finally {
                $curl->close();
            }

            self::assertFileExists($filename);
            self::assertStringContainsString("\tminted\tyes", (string) \file_get_contents($filename));
        } finally {
            if (\is_file($filename)) {
                \unlink($filename);
            }
        }
    }

    public function testNewRequestStartsWithZeroCurlAttempts(): void
    {
        $request = Request::get(self::url())->_curlPrep();

        try {
            self::assertSame(0, $request->_curl()->getAttempts());
        } finally {
            $request->close();
        }
    }
}
