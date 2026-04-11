<?php

require_once __DIR__ . '/../../../includes/env.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/Migration.php';

load_env(__DIR__ . '/../../../.env');

echo "=== CCS Sit-In Monitoring — Database Migrations ===\n\n";

$migration = new Migration($db);

// Check for 'fresh' command line argument
if (isset($argv[1]) && $argv[1] === 'fresh') {
    $migration->fresh();
} else {
    $migration->run();
}