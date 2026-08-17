<?php

declare(strict_types=1);

$autoloadRuntime = dirname(__DIR__) . '/vendor/autoload_runtime.php';
$contents = file_get_contents($autoloadRuntime);

if ($contents === false) {
    fwrite(STDERR, "Could not read vendor/autoload_runtime.php\n");
    exit(1);
}

if (str_contains($contents, '.worktrees/')) {
    fwrite(STDERR, "Runtime autoload must not point to a git worktree.\n");
    exit(1);
}

if (!str_contains($contents, "'project_dir' => dirname(__DIR__, 1),")) {
    fwrite(STDERR, "Runtime autoload must use the main project directory.\n");
    exit(1);
}

fwrite(STDOUT, "OK\n");
