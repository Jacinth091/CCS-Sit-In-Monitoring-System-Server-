<?php
// src/database/seeders/seed.php

require_once __DIR__ . '/../../../includes/env.php';
require_once __DIR__ . '/../migrations/db.php';
require_once __DIR__ . '/Seeder.php';

load_env(__DIR__ . '/../../../.env');

echo "=== CCS Sit-In Monitoring — Seeders ===\n\n";

$seeder = new Seeder($db);

// ── Options ──────────────────────────────────────────────────────────────────
// Run all pending seeders (default):
$seeder->run();

// Run a single seeder by filename (without .php):
// $seeder->run('002_LaboratorySeeder');

// Re-run ALL seeders from scratch (clears history first):
// $seeder->fresh();
// ─────────────────────────────────────────────────────────────────────────────
