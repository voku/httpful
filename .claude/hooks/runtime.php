<?php

declare(strict_types=1);

use voku\AgentLoop\AgentGuidance\AgentDisciplineHook;

$repositoryRoot = dirname(__DIR__, 2);

// Projected hooks are optional repository tooling. A host may run an older PHP
// than agent-loop supports, so never load its Composer runtime below the package
// floor: Composer's generated platform check can abort before the hook can no-op.
if (PHP_VERSION_ID < 80300) {
    return false;
}

$rootAutoload = $repositoryRoot . '/vendor/autoload.php';
$autoloadCandidates = [];
if (is_dir($repositoryRoot . '/vendor/voku/agent-loop')) {
    $autoloadCandidates[] = $rootAutoload;
}

// An isolated tool project is the supported escape hatch when the host's PHP
// dependency graph cannot include agent-loop. Keep this path explicit instead
// of executing arbitrary tools/* Composer autoloaders.
$autoloadCandidates[] = $repositoryRoot . '/tools/agent-loop/vendor/autoload.php';
if (!in_array($rootAutoload, $autoloadCandidates, true)) {
    $autoloadCandidates[] = $rootAutoload;
}

foreach ($autoloadCandidates as $autoload) {
    if (!is_file($autoload)) {
        continue;
    }

    require_once $autoload;
    if (class_exists(AgentDisciplineHook::class)) {
        return true;
    }
}

$sourceRoot = $repositoryRoot . '/src';
if (is_dir($sourceRoot)) {
    // Root-package checkout with no installed dependencies. Register the whole
    // PSR-4 root rather than maintaining a hand-written collaborator list.
    spl_autoload_register(static function (string $class) use ($sourceRoot): void {
        $prefix = 'voku\\AgentLoop\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
        $file = $sourceRoot . '/' . $relative . '.php';
        if (is_file($file)) {
            require $file;
        }
    });
}

return class_exists(AgentDisciplineHook::class);
