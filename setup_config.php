<?php
/**
 * Quick Setup Script
 * 
 * Run this on a new machine to create config files from templates:
 *   php setup_config.php
 * 
 * After running, update the real files with your actual credentials.
 */

echo "=== CCS Sit-In Monitoring System — Config Setup ===\n\n";

$files = [
    ['.env.example',                      '.env'],
    ['config/ai.example.php',             'config/ai.php'],
    ['config/ai_limits.example.php',      'config/ai_limits.php'],
    ['config/ai_settings.example.json',   'config/ai_settings.json'],
];

$created = 0;
$skipped = 0;

foreach ($files as [$source, $target]) {
    $sourcePath = __DIR__ . '/' . $source;
    $targetPath = __DIR__ . '/' . $target;

    if (!file_exists($sourcePath)) {
        echo "  [SKIP]  Template not found: {$source}\n";
        $skipped++;
        continue;
    }

    if (file_exists($targetPath)) {
        echo "  [SKIP]  Already exists: {$target}\n";
        $skipped++;
        continue;
    }

    copy($sourcePath, $targetPath);
    echo "  [CREATED]  {$target}  (from {$source})\n";
    $created++;
}

echo "\n--- Done: {$created} created, {$skipped} skipped ---\n";

if ($created > 0) {
    echo "\n⚠  IMPORTANT: Update these files with your actual credentials:\n";
    echo "   • .env           → DB password, API keys, JWT secret\n";
    echo "   • config/ai.php  → API keys are loaded from .env, so just update .env\n";
    echo "\n";
}
