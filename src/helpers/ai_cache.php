<?php
/**
 * Shared utility to persist and manage rate limits telemetry
 */
if (!function_exists('updateAiRateLimitsCache')) {
  function updateAiRateLimitsCache(string $provider, array $headers, string $modelUsed, int $httpCode = 200) {
    $cacheFile = __DIR__ . '/../../config/ai_rate_limits_cache.json';
    
    $cache = [];
    if (file_exists($cacheFile)) {
      $cache = json_decode(file_get_contents($cacheFile), true) ?? [];
    }

    // Determine status
    $status = 'operational';
    if ($httpCode === 429) {
      $status = 'rate_limited';
    } elseif ($httpCode >= 400) {
      $status = 'error';
    }

    // Parse retry-after from headers or payload if present
    $retryAfter = null;
    if (isset($headers['retry-after'])) {
      $retryAfter = (int)$headers['retry-after'];
    } elseif ($httpCode === 429) {
      $retryAfter = 60; // default 60 seconds backup cooldown
    }

    // Calculate retry timestamp
    $retryUntil = null;
    if ($retryAfter !== null) {
      $retryUntil = time() + $retryAfter;
    }

    $existing = $cache[$provider] ?? [];
    
    $cache[$provider] = [
      'model'              => $modelUsed,
      'updated_at'         => date('Y-m-d H:i:s'),
      'status'             => $status,
      'http_code'          => $httpCode,
      'retry_until'        => $retryUntil,
      'limit_requests'     => $headers['x-ratelimit-limit-requests'] ?? ($existing['limit_requests'] ?? 'N/A'),
      'limit_tokens'       => $headers['x-ratelimit-limit-tokens'] ?? ($existing['limit_tokens'] ?? 'N/A'),
      'remaining_requests' => $httpCode === 429 ? '0' : ($headers['x-ratelimit-remaining-requests'] ?? ($existing['remaining_requests'] ?? 'N/A')),
      'remaining_tokens'   => $httpCode === 429 ? '0' : ($headers['x-ratelimit-remaining-tokens'] ?? ($existing['remaining_tokens'] ?? 'N/A')),
      'reset_requests'     => $headers['x-ratelimit-reset-requests'] ?? ($existing['reset_requests'] ?? 'N/A'),
      'reset_tokens'       => $headers['x-ratelimit-reset-tokens'] ?? ($existing['reset_tokens'] ?? 'N/A'),
      'retry_after'        => $retryAfter,
    ];

    if ($provider === 'gemini') {
      $cache[$provider]['is_local'] = true;
    }

    file_put_contents($cacheFile, json_encode($cache, JSON_PRETTY_PRINT));
  }
}
