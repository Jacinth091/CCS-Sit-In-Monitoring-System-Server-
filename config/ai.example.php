<?php
// =============================================================
// AI Provider Configuration
// Server-side only. Never expose to frontend. Never commit.
//
// SETUP: Copy this file to ai.php
//        cp config/ai.example.php config/ai.php
// =============================================================

// ── Groq (Chatbot) ───────────────────────────────────────────
// Free tier: https://console.groq.com
// Used for: real-time chat — fastest inference latency
define('GROQ_API_KEY',    $_ENV['GROQ_API_KEY'] ?? '');
define('GROQ_API_URL',    'https://api.groq.com/openai/v1/chat/completions');
define('GROQ_CHAT_MODEL', 'llama-3.3-70b-versatile');
define('GROQ_CHAT_MODEL_FALLBACK', 'meta-llama/llama-4-scout-17b-16e-instruct');

// ── Gemini (Analysis + Summaries) ────────────────────────────
// Free tier: https://aistudio.google.com/app/apikey
// Used for: one-shot analysis cards and report summaries
define('GEMINI_API_KEY',  $_ENV['GEMINI_API_KEY'] ?? '');
define('GEMINI_API_URL',  'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent');

// ── Global AI settings ────────────────────────────────────────
function isAiEnabled(): bool {
    $settingsFile = __DIR__ . '/ai_settings.json';
    if (file_exists($settingsFile)) {
        $settings = json_decode(file_get_contents($settingsFile), true);
        if (isset($settings['ai_enabled'])) {
            return (bool) $settings['ai_enabled'];
        }
    }
    return filter_var($_ENV['AI_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN);
}

define('AI_ENABLED',           isAiEnabled());
define('AI_TIMEOUT_SEC',       30);
define('CHAT_MAX_TOKENS',      600);
define('ANALYSIS_MAX_TOKENS',  2048);
define('SUMMARY_MAX_TOKENS',   1000);
