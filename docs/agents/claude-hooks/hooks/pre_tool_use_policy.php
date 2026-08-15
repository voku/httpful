<?php

declare(strict_types=1);

use voku\AgentLoop\AgentGuidance\AgentDisciplineHook;

$repositoryRoot = dirname(__DIR__, 2);

// See context.php for why the runtime is probed instead of required from a
// fixed path, and why this block is duplicated rather than shared.
$runtimeReady = false;
$autoloadCandidates = [$repositoryRoot . '/vendor/autoload.php'];
foreach ((array) glob($repositoryRoot . '/tools/*/vendor/autoload.php') as $toolAutoload) {
    $autoloadCandidates[] = $toolAutoload;
}
foreach ($autoloadCandidates as $autoload) {
    if (!is_file($autoload)) {
        continue;
    }

    require_once $autoload;

    if (class_exists(AgentDisciplineHook::class)) {
        $runtimeReady = true;
        break;
    }
}

if (!$runtimeReady) {
    // The tool project is not installed yet. The hook carries no security
    // boundary, so an unavailable runtime must not block ordinary tool use.
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
