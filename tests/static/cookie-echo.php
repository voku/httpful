<?php

declare(strict_types=1);

\header('Content-Type: text/plain; charset=utf-8');

if (isset($_GET['set_cookie'])) {
    \header('Set-Cookie: minted=yes; Path=/');
}

echo $_SERVER['HTTP_COOKIE'] ?? '';
