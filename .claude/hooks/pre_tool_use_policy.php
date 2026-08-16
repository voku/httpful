<?php

declare(strict_types=1);

use voku\AgentLoop\AgentGuidance\AgentDisciplineHook;

$repositoryRoot = dirname(__DIR__, 2);
$runtimeReady = require __DIR__ . '/runtime.php';
if ($runtimeReady !== true) {
    // This hook is a workflow guardrail, not a security boundary. Optional
    // tooling being unavailable must not block ordinary host tool use.
    exit(0);
}

$rawPayload = stream_get_contents(STDIN, 1_048_577);
if (!is_string($rawPayload)) {
    fwrite(STDERR, "Unable to read hook payload.\n");
    exit(1);
}

try {
    echo json_encode(
        (new AgentDisciplineHook($repositoryRoot))->preToolUseOutput($rawPayload),
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
    ) . "\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, 'agent-loop Claude PreToolUse hook failed: ' . $throwable->getMessage() . "\n");
    exit(1);
}
