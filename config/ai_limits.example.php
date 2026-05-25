<?php
// =============================================================
// AI Rate Limiting & Quota Configuration
// Adjust these values without touching middleware logic
//
// SETUP: Copy this file to ai_limits.php
//        cp config/ai_limits.example.php config/ai_limits.php
// =============================================================

// Load environment variables if not loaded
if (empty($_ENV['HMAC_SECRET'])) {
    $envPath = __DIR__ . '/../.env';
    if (file_exists($envPath)) {
        // Simple line parser if load_env hasn't run yet
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) continue;
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $val = trim($parts[1], " \t\n\r\0\x0B\"'");
                $_ENV[$key] = $val;
            }
        }
    }
}

// ── Per-account daily quotas (resets at midnight server time) ─
define('QUOTA_STUDENT_CHAT_DAILY',      15);  // chat messages per student per day
define('QUOTA_STUDENT_INSIGHTS_DAILY',   3);  // insight generates per student per day
define('QUOTA_ADMIN_CHAT_DAILY',        50);  // admins get more
define('QUOTA_ADMIN_INSIGHTS_DAILY',    10);
define('QUOTA_ADMIN_SUMMARY_DAILY',     20);  // report summaries — admin only

// ── Per-action cooldowns (seconds between same action) ────────
define('COOLDOWN_CHAT_SECONDS',          0);  // No mandatory wait between messages
define('CHAT_BURST_THRESHOLD',          5);  // Max messages in a short window
define('CHAT_BURST_WINDOW_SECONDS',    15);  // The window to check for bursts
define('CHAT_BURST_PENALTY_SECONDS',  300);  // 5 minute block if spamming
define('COOLDOWN_INSIGHTS_SECONDS',    600);  // 10 min between insight generates
define('COOLDOWN_SUMMARY_SECONDS',     120);  // 2 min between report summaries

// ── Global system budget (across ALL users per day) ───────────
define('GLOBAL_BUDGET_CHAT_DAILY',    1000);  // total chat calls/day system-wide
define('GLOBAL_BUDGET_ANALYSIS_DAILY', 200);  // total insight calls/day system-wide
define('GLOBAL_BUDGET_SUMMARY_DAILY',  100);  // total summary calls/day system-wide

// ── Abuse detection thresholds ────────────────────────────────
define('ABUSE_FAILURE_WINDOW_SECONDS',  300); // 5 minute window
define('ABUSE_FAILURE_THRESHOLD',        10); // failures before temp block
define('ABUSE_BLOCK_DURATION_SECONDS',  900); // 15 min block after threshold hit

// ── Request signing ───────────────────────────────────────────
define('HMAC_SECRET',        $_ENV['HMAC_SECRET'] ?? '7f8b9e6a3d1c4f5b2a0d9e8f7c6b5a4d3e2f1a0b9c8d7e6f5a4b3c2d1e0f9a8b');
define('HMAC_TIMESTAMP_WINDOW', 60);          // reject requests older than 60 seconds

// Prompt injection punishment cooldown
define('INJECTION_COOLDOWN_SECONDS', 600); // 10 minutes
