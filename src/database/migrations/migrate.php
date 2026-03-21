<?php

require_once __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/Migration.php';

load_env(__DIR__ . '/../.env');

echo "=== CCS Sit-In Monitoring — Database Migrations ===\n\n";

$migration = new Migration($db);
$migration->run();