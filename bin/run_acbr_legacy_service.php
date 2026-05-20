#!/usr/bin/env php
<?php

if ($argc < 3) {
    fwrite(STDERR, "Usage: run_acbr_legacy_service.php <script> <base64-payload>\n");
    exit(1);
}

$script = $argv[1];
$payload = json_decode(base64_decode($argv[2], true) ?: '', true);

if (!is_file($script)) {
    fwrite(STDERR, "Legacy script not found: {$script}\n");
    exit(1);
}

if (!is_array($payload)) {
    fwrite(STDERR, "Invalid payload.\n");
    exit(1);
}

$_POST = $payload;
$_SERVER['REQUEST_METHOD'] = 'POST';

chdir(dirname($script));
include basename($script);
