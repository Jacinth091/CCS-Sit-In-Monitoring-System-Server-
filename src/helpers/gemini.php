<?php
require_once __DIR__ . '/../../config/ai.php';
require_once __DIR__ . '/ai_cache.php';

/**
 * Global to store last raw error for debugging in development
 */
$lastGeminiError = null;

function attemptGroqFallbackForAnalysis(string $prompt, ?int $maxTokens): ?string {
  try {
    require_once __DIR__ . '/groq.php';
    error_log("Gemini unavailable or rate-limited. Attempting fallback to Groq Llama Scout for analysis...");

    $systemPrompt = "You are an analytical assistant for a university computer laboratory monitoring system. Output response exactly as requested.";
    $messages = [['role' => 'user', 'content' => $prompt]];

    // Attempt the Llama Scout model directly first
    $fallbackModel = defined('GROQ_CHAT_MODEL_FALLBACK') ? GROQ_CHAT_MODEL_FALLBACK : 'meta-llama/llama-4-scout-17b-16e-instruct';
    $response = callGroqWithModel($systemPrompt, $messages, $fallbackModel, $maxTokens ?? 1500, true);
    if ($response !== null) {
      error_log("Fallback to Groq Llama Scout succeeded.");
      return $response;
    }

    // Try the primary model if Scout fails
    error_log("Groq Llama Scout model failed. Attempting primary Groq model...");
    $response = callGroqWithModel($systemPrompt, $messages, GROQ_CHAT_MODEL, $maxTokens ?? 1500, false);
    if ($response !== null) {
      error_log("Fallback to primary Groq model succeeded.");
      return $response;
    }
  } catch (\Exception $e) {
    error_log("Error during Groq fallback: " . $e->getMessage());
  }
  return null;
}

/**
 * Call Gemini API. Returns raw text or null on failure.
 * Returns null immediately if AI_ENABLED = false.
 */
function callGemini(string $prompt, ?int $maxTokens = null): ?string {
  global $lastGeminiError;
  $lastGeminiError = null;

  if (!AI_ENABLED) return null;

  // Proactive Rate Limit Check
  $cacheFile = __DIR__ . '/../../config/ai_rate_limits_cache.json';
  if (file_exists($cacheFile)) {
    $cache = json_decode(file_get_contents($cacheFile), true);
    if (isset($cache['gemini']['status']) && $cache['gemini']['status'] === 'rate_limited') {
      if (isset($cache['gemini']['retry_until']) && time() < $cache['gemini']['retry_until']) {
        $lastGeminiError = [
          'provider'  => 'gemini',
          'http_code' => 429,
          'curl_err'  => 'Rate Limit Cooldown Active (Local Check)',
          'response'  => 'Requests temporarily paused. Exceeded limits.'
        ];
        // Attempt Groq fallback instead of returning null directly
        return attemptGroqFallbackForAnalysis($prompt, $maxTokens);
      }
    }
  }

  $genConfig = [
    'temperature'      => 0.4,
    'responseMimeType' => 'application/json',
  ];

  if ($maxTokens !== null) {
    $genConfig['maxOutputTokens'] = $maxTokens;
  }

  $payload = [
    'contents'         => [['parts' => [['text' => $prompt]]]],
    'generationConfig' => $genConfig,
  ];

  $responseHeaders = [];
  $ch = curl_init(GEMINI_API_URL . '?key=' . GEMINI_API_KEY);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => AI_TIMEOUT_SEC,
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
  updateAiRateLimitsCache('gemini', $responseHeaders, 'gemini-2.5-flash', $httpCode);

  if ($httpCode !== 200 || !$response) {
    $lastGeminiError = [
      'provider'  => 'gemini',
      'http_code' => $httpCode,
      'curl_err'  => $error,
      'response'  => json_decode($response, true) ?: $response
    ];
    error_log("Gemini API Error: HTTP $httpCode, CURL Error: $error, Response: $response");
    
    // Attempt fallback to Groq
    return attemptGroqFallbackForAnalysis($prompt, $maxTokens);
  }

  $data = json_decode($response, true);
  $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
  $finishReason = $data['candidates'][0]['finishReason'] ?? 'UNKNOWN';

  if ($finishReason !== 'STOP' && $finishReason !== 'MAX_TOKENS') {
      // Potentially truncated or failed for other reasons
      error_log("Gemini Finish Reason: $finishReason. Full Response: $response");
  }

  if ($text === null) {
      return attemptGroqFallbackForAnalysis($prompt, $maxTokens);
  }

  return $text;
}

/**
 * Strip ```json ... ``` fences Gemini adds even when told not to.
 * Always call this before json_decode() on any Gemini response.
 */
function stripMarkdownFences(string $text): string {
  return trim(preg_replace('/^```(?:json)?\s*/m', '', preg_replace('/\s*```$/m', '', $text)));
}

function getLastGeminiError() {
  global $lastGeminiError;
  return $lastGeminiError;
}
