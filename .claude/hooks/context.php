<?php

declare(strict_types=1);

use voku\AgentLoop\AgentGuidance\AgentDisciplineHook;

$repositoryRoot = dirname(__DIR__, 2);
$runtimeReady = require __DIR__ . '/runtime.php';
if ($runtimeReady !== true) {
    // Projected hooks are optional tooling. A clean checkout may run them before
    // Composer installed the tool project, and the host PHP may be below the
    // agent-loop runtime floor. Neither condition should break the host session.
    exit(0);
}

$event = 'SessionStart';
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--event=')) {
        $event = substr($argument, strlen('--event='));
    }
}

$rawPayload = stream_get_contents(STDIN, 1_048_577);
if (!is_string($rawPayload)) {
    fwrite(STDERR, "Unable to read hook payload.\n");
    exit(1);
}

try {
    echo json_encode(
        (new AgentDisciplineHook($repositoryRoot))->claudeContextOutput($event, $rawPayload),
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
    ) . "\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, 'agent-loop Claude context hook failed: ' . $throwable->getMessage() . "\n");
    exit(1);
}
