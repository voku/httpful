<?php

declare(strict_types=1);

namespace Httpful\tests;

/**
 * An ephemeral local HTTPS endpoint with an untrusted certificate.
 *
 * Exercises real libcurl certificate handling without relying on an external
 * website, DNS, proxy availability or shared TLS endpoints.
 *
 * @internal
 */
final class LocalSelfSignedTlsServer
{
    /**
     * @param callable(string): void $test
     */
    public static function withServer(callable $test): void
    {
        $directory = \sys_get_temp_dir() . '/httpful-tls-' . \bin2hex(\random_bytes(8));
        if (!\mkdir($directory, 0700)) {
            throw new \RuntimeException('Unable to create isolated TLS test directory.');
        }

        $key = $directory . '/key.pem';
        $certificate = $directory . '/cert.pem';
        $log = $directory . '/openssl.log';
        $server = null;

        try {
            $generated = \proc_open(
                [
                    'openssl', 'req', '-x509', '-newkey', 'rsa:2048', '-nodes',
                    '-days', '1', '-subj', '/CN=localhost',
                    '-keyout', $key, '-out', $certificate,
                ],
                [
                    0 => ['file', '/dev/null', 'r'],
                    1 => ['file', $log, 'a'],
                    2 => ['file', $log, 'a'],
                ],
                $pipes
            );

            if (!\is_resource($generated) || \proc_close($generated) !== 0) {
                throw new \RuntimeException('Generating the self-signed TLS fixture failed.');
            }

            $listener = \stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
            if (!\is_resource($listener)) {
                throw new \RuntimeException('Unable to allocate a loopback TLS port: ' . $error);
            }

            $address = \stream_socket_get_name($listener, false);
            \fclose($listener);

            if (!\is_string($address) || !\str_contains($address, ':')) {
                throw new \RuntimeException('Unable to determine the local TLS port.');
            }

            $port = (int) \substr($address, (int) \strrpos($address, ':') + 1);
            if ($port < 1 || $port > 65535) {
                throw new \RuntimeException('Invalid local TLS port allocated.');
            }

            $server = \proc_open(
                [
                    'openssl', 's_server', '-www',
                    '-accept', '127.0.0.1:' . $port,
                    '-cert', $certificate, '-key', $key,
                ],
                [
                    0 => ['file', '/dev/null', 'r'],
                    1 => ['file', $log, 'a'],
                    2 => ['file', $log, 'a'],
                ],
                $pipes
            );

            if (!\is_resource($server)) {
                throw new \RuntimeException('Starting the local TLS server failed.');
            }

            $ready = false;
            for ($attempt = 0; $attempt < 100; ++$attempt) {
                $socket = @\stream_socket_client(
                    'tcp://127.0.0.1:' . $port,
                    $errno,
                    $error,
                    0.1
                );

                if (\is_resource($socket)) {
                    \fclose($socket);
                    $ready = true;
                    break;
                }

                \usleep(20000);
            }

            if (!$ready) {
                throw new \RuntimeException('The local TLS server did not become ready.');
            }

            $test('https://127.0.0.1:' . $port . '/');
        } finally {
            if (\is_resource($server)) {
                \proc_terminate($server);
                \proc_close($server);
            }

            foreach ([$key, $certificate, $log] as $file) {
                if (\is_file($file)) {
                    \unlink($file);
                }
            }

            \rmdir($directory);
        }
    }
}
