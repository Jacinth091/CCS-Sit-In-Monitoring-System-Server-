<?php
require_once __DIR__ . '/../../config/ai.php';
require_once __DIR__ . '/ai_cache.php';

/**
 * Global to store last raw error for debugging in development
 */
$lastGroqError = null;

/**
 * Call Groq API. Returns assistant reply text or null on failure.
 * Returns null immediately if AI_ENABLED = false.
 */
function callGroq(string $systemPrompt, array $messages, int $maxTokens = CHAT_MAX_TOKENS): ?string {
  if (!AI_ENABLED) return null;

  // Try the primary model
  $reply = callGroqWithModel($systemPrompt, $messages, GROQ_CHAT_MODEL, $maxTokens, false);
  if ($reply !== null) {
    return $reply;
  }

  // Fallback if configured and different
  if (defined('GROQ_CHAT_MODEL_FALLBACK') && GROQ_CHAT_MODEL_FALLBACK !== GROQ_CHAT_MODEL) {
    error_log("Primary Groq model (" . GROQ_CHAT_MODEL . ") failed. Attempting fallback model (" . GROQ_CHAT_MODEL_FALLBACK . ")...");
    return callGroqWithModel($systemPrompt, $messages, GROQ_CHAT_MODEL_FALLBACK, $maxTokens, true);
  }

  return null;
}

/**
 * Execute request to Groq for a specific model.
 */
function callGroqWithModel(string $systemPrompt, array $messages, string $model, int $maxTokens, bool $isFallback): ?string {
  // Proactive Rate Limit Check
  $providerKey = $isFallback ? 'groq_fallback' : 'groq_primary';
  $cacheFile = __DIR__ . '/../../config/ai_rate_limits_cache.json';
  if (file_exists($cacheFile)) {
    $cache = json_decode(file_get_contents($cacheFile), true);
    if (isset($cache[$providerKey]['status']) && $cache[$providerKey]['status'] === 'rate_limited') {
      if (isset($cache[$providerKey]['retry_until']) && time() < $cache[$providerKey]['retry_until']) {
        global $lastGroqError;
        $lastGroqError = [
          'provider'  => 'groq',
          'model'     => $model,
          'http_code' => 429,
          'curl_err'  => 'Rate Limit Cooldown Active (Local Check)',
          'response'  => 'Requests temporarily paused. Exceeded limits.'
        ];
        return null;
      }
    }
  }

  $payload = [
    'model'       => $model,
    'max_tokens'  => $maxTokens,
    'temperature' => 0.7,
    'messages'    => array_merge(
      [['role' => 'system', 'content' => $systemPrompt]],
      array_slice($messages, -10) // cap at last 10 messages
    ),
  ];

  $responseHeaders = [];
  $ch = curl_init(GROQ_API_URL);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
      'Content-Type: application/json',
      'Authorization: Bearer ' . GROQ_API_KEY,
    ],
    CURLOPT_TIMEOUT => AI_TIMEOUT_SEC,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HEADERFUNCTION => function($curl, $header) use (&$responseHeaders) {
      $len = strlen($header);
      $parts = explode(':', $header, 2);
      if (count($parts) < 2) return $len;
      $name = strtolower(trim($parts[0]));
      $value = trim($parts[1]);
      $responseHeaders[$name] = $value;
      return $len;
    }
  ]);

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $error    = curl_error($ch);
  curl_close($ch);

  // Update rate limit cache file with HTTP Code status
  updateAiRateLimitsCache($isFallback ? 'groq_fallback' : 'groq_primary', $responseHeaders, $model, $httpCode);

  if ($httpCode !== 200 || !$response) {
    global $lastGroqError;
    $lastGroqError = [
      'provider'  => 'groq',
      'model'     => $model,
      'http_code' => $httpCode,
      'curl_err'  => $error,
      'response'  => json_decode($response, true) ?: $response
    ];
    error_log(($isFallback ? "[Fallback] " : "") . "Groq API Error ($model): HTTP $httpCode, CURL Error: $error, Response: $response");
    return null;
  }

  $data = json_decode($response, true);
  return $data['choices'][0]['message']['content'] ?? null;
}



function getLastGroqError() {
  global $lastGroqError;
  return $lastGroqError;
}
