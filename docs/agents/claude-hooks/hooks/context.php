<?php

declare(strict_types=1);

use voku\AgentLoop\AgentGuidance\AgentDisciplineHook;

$repositoryRoot = dirname(__DIR__, 2);

// httpful still supports PHP ^8.0 while voku/agent-loop requires ^8.3, so
// agent-loop is installed as the isolated tool project below tools/agent-loop/
// instead of as a root require-dev dependency. The repository's own
// vendor/autoload.php exists but knows nothing about voku\AgentLoop\*, so probe
// every candidate autoloader and confirm the class really resolved. This is
// duplicated in pre_tool_use_policy.php because `init sync-hooks` only copies
// the files named as commands in hooks.json, not a shared helper next to them.
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
    // The tool project is not installed yet. Injecting no context is correct
    // here; failing the hook would break every session over optional tooling.
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
