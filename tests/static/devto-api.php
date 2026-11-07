<?php

declare(strict_types=1);

// Local Dev.to-like fixture served by tests/bootstrap.php's built-in PHP server.
\header('Content-Type: application/json; charset=utf-8');

$id = $_GET['id'] ?? null;

if ($id !== null) {
    if (!\is_string($id) || !\in_array($id, ['101', '102'], true)) {
        \http_response_code(404);
        echo \json_encode(['error' => 'unknown article'], \JSON_THROW_ON_ERROR);

        return;
    }

    echo \json_encode(['id' => (int) $id, 'title' => 'Fixture article ' . $id], \JSON_THROW_ON_ERROR);

    return;
}

echo \json_encode([
    ['id' => 101, 'title' => 'Fixture article 101'],
    ['id' => 102, 'title' => 'Fixture article 102'],
], \JSON_THROW_ON_ERROR);
