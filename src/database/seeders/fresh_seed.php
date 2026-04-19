<?php
// src/database/seeders/fresh_seed.php

require_once __DIR__ . '/../../../includes/env.php';
require_once __DIR__ . '/../migrations/db.php';
require_once __DIR__ . '/Seeder.php';

load_env(__DIR__ . '/../../../.env');

echo "=== CCS Sit-In Monitoring — Refreshing Seeders ===\n\n";

$seeder = new Seeder($db);
$seeder->fresh();
