<?php

require_once __DIR__ . '/../../config/ai_limits.php';

class AiAuthMiddleware {

  /**
   * Primary guard — call this at the top of every AI endpoint.
   *
   * @param string $endpoint  'chat' | 'student_insights' | 'admin_insights' | 'report_summary'
   * @param PDO    $pdo       DB connection
   * @param array  $payload   Parsed request body (for HMAC verification)
   * @return array            ['user_id' => string, 'role' => string]
   *                          Returns only on success — exits with JSON error otherwise
   */
  public static function guard(string $endpoint, PDO $pdo, array $payload = []): array {

    $ip = self::getClientIp();

    // ── Step 1: Abuse check ──────────────────────────────────
    self::checkAbuseBlock($ip, $ip, $pdo);

    // ── Step 2: JWT extraction & validation ──────────────────
    $token = self::extractToken();
    if (!$token) {
      self::logAbuse($ip, 'invalid_token', $ip, $pdo);
      self::reject(401, 'Authentication required.');
    }

    $claims = self::validateJwt($token);
    if (!$claims) {
      self::logAbuse($ip, 'invalid_token', $ip, $pdo);
      self::reject(401, 'Invalid or expired token.');
    }

    $userId = (string) ($claims['sub'] ?? '');
    $role   = $claims['role'] ?? '';

    if (!$userId || !in_array($role, ['student', 'admin'], true)) {
      self::logAbuse($userId, 'invalid_token', $ip, $pdo);
      self::reject(401, 'Invalid token claims.');
    }

    // ── Step 3: Active session DB check ─────────────────────
    $tokenHash = hash('sha256', $token);
    self::checkActiveSession($userId, $role, $tokenHash, $ip, $pdo);

    // ── Step 4: HMAC request signature verification ──────────
    self::verifyHmac($payload, $userId, $pdo);

    // ── Step 5: Endpoint access control ──────────────────────
    self::checkEndpointAccess($endpoint, $role, $userId, $pdo);

    // ── Step 6: Global system budget check ───────────────────
    self::checkGlobalBudget($endpoint, $pdo);

    // ── Step 7: Per-account daily quota check ────────────────
    self::checkDailyQuota($endpoint, $userId, $role, $pdo);

    // ── Step 8: Per-account cooldown check ───────────────────
    self::checkCooldown($endpoint, $userId, $role, $pdo);

    // ── All checks passed — update session last_used_at ──────
    self::touchSession($tokenHash, $pdo);

    return ['user_id' => $userId, 'role' => $role];
  }

  // ════════════════════════════════════════════════════════════
  // Step implementations
  // ════════════════════════════════════════════════════════════

  private static function extractToken(): ?string {
    $header = null;
    if (isset($_SERVER['Authorization'])) {
      $header = trim($_SERVER["Authorization"]);
    } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { 
      $header = trim($_SERVER["HTTP_AUTHORIZATION"]);
    } elseif (function_exists('apache_request_headers')) {
      $requestHeaders = apache_request_headers();
      if (isset($requestHeaders['Authorization'])) {
        $header = trim($requestHeaders['Authorization']);
      }
    }

    if ($header && preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
      return $m[1];
    }
    return null;
  }

  private static function validateJwt(string $token): ?array {
    try {
      $secretKey = $_ENV['JWT_SECRET'] ?? 'default_secret_key_change_me';
      $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($secretKey, 'HS256'));
      if (isset($decoded->data)) {
        return [
          'sub' => (string) ($decoded->data->id ?? ''),
          'role' => $decoded->data->role ?? null
        ];
      }
    } catch (\Exception $e) {
      // invalid or expired
    }
    return null;
  }

  private static function checkActiveSession(
    string $userId, string $role, string $tokenHash, string $ip, PDO $pdo
  ): void {
    $stmt = $pdo->prepare(
      "SELECT id, device_fingerprint FROM user_sessions
       WHERE token_hash = ? AND user_id = ? AND is_active = TRUE
         AND expires_at > NOW()
       LIMIT 1"
    );
    $stmt->execute([$tokenHash, $userId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
      self::logAbuse($userId, 'expired_session', $ip, $pdo);
      self::reject(401, 'Session expired or revoked. Please log in again.');
    }

    $currentFingerprint = self::buildFingerprint();
    if ($session['device_fingerprint'] &&
        $session['device_fingerprint'] !== $currentFingerprint) {
      self::logAbuse($userId, 'fingerprint_mismatch', $ip, $pdo);
    }
  }

  private static function verifyHmac(array $payload, string $userId, PDO $pdo): void {
    $clientSig = $_SERVER['HTTP_X_AI_SIGNATURE'] ?? '';
    $timestamp  = (int) ($_SERVER['HTTP_X_AI_TIMESTAMP'] ?? 0);

    if (!$clientSig || !$timestamp) {
      self::logAbuse($userId, 'invalid_signature', self::getClientIp(), $pdo);
      self::reject(403, 'Request signature missing.');
    }

    if (abs(time() - $timestamp) > HMAC_TIMESTAMP_WINDOW) {
      self::logAbuse($userId, 'invalid_signature', self::getClientIp(), $pdo);
      self::reject(403, 'Request expired. Please try again.');
    }

    // Match JS behavior for empty objects vs arrays
    $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($jsonPayload === '[]' && empty($payload)) {
        $jsonPayload = '{}';
    }

    $signingString = $timestamp . '.' . $userId . '.' . $jsonPayload;
    $expectedSig   = hash_hmac('sha256', $signingString, HMAC_SECRET);

    if (!hash_equals($expectedSig, $clientSig)) {
      self::logAbuse($userId, 'invalid_signature', self::getClientIp(), $pdo);
      self::reject(403, 'Invalid request signature.');
    }
  }

  private static function checkEndpointAccess(
    string $endpoint, string $role, string $userId, PDO $pdo
  ): void {
    $adminOnly = ['admin_insights', 'report_summary'];
    if (in_array($endpoint, $adminOnly, true) && $role !== 'admin') {
      self::logAbuse($userId, 'unauthorized_endpoint', self::getClientIp(), $pdo);
      self::reject(403, 'Access denied.');
    }
  }

  private static function checkGlobalBudget(string $endpoint, PDO $pdo): void {
    $pdo->exec(
      "INSERT INTO ai_global_budget (budget_date)
       VALUES (CURRENT_DATE)
       ON CONFLICT (budget_date) DO NOTHING"
    );

    $col   = match($endpoint) {
      'chat'             => ['col' => 'chat_calls',     'limit' => GLOBAL_BUDGET_CHAT_DAILY],
      'student_insights',
      'booking_recommendations',
      'admin_insights'   => ['col' => 'analysis_calls', 'limit' => GLOBAL_BUDGET_ANALYSIS_DAILY],
      'report_summary'   => ['col' => 'summary_calls',  'limit' => GLOBAL_BUDGET_SUMMARY_DAILY],
      default            => null,
    };

    if (!$col) return;

    $stmt = $pdo->prepare(
      "SELECT {$col['col']} FROM ai_global_budget WHERE budget_date = CURRENT_DATE"
    );
    $stmt->execute();
    $current = (int) $stmt->fetchColumn();

    if ($current >= $col['limit']) {
      self::reject(429,
        'The AI service has reached its daily system limit. It resets at midnight. Please try again tomorrow.',
        ['resets_at' => self::midnightTimestamp()]
      );
    }
  }

  private static function checkCooldown(
    string $endpoint, string $userId, string $role, PDO $pdo
  ): void {
    // ── Burst Protection for Chat ──
    if ($endpoint === 'chat') {
      $stmtBurst = $pdo->prepare(
        "SELECT COUNT(*) FROM ai_usage_log
         WHERE user_id = ? AND endpoint = 'chat' AND was_blocked = FALSE
           AND requested_at >= NOW() - INTERVAL '" . CHAT_BURST_WINDOW_SECONDS . " seconds'"
      );
      $stmtBurst->execute([$userId]);
      $burstCount = (int) $stmtBurst->fetchColumn();

      if ($burstCount >= CHAT_BURST_THRESHOLD) {
        self::logUsage($userId, $role, $endpoint, $pdo, true, 'burst_spam');
        self::reject(429,
          "Spam detected. You are sending messages too quickly. Please wait a few minutes.",
          ['retry_after_seconds' => CHAT_BURST_PENALTY_SECONDS]
        );
      }
    }

    // ── Injection punishment cooldown check ──────────────────────
    // Separate from the regular cooldown — checked first, higher priority.
    $injStmt = $pdo->prepare(
      "SELECT requested_at FROM ai_usage_log
       WHERE user_id = ? AND endpoint = 'chat'
         AND was_blocked = TRUE AND block_reason = 'prompt_injection'
       ORDER BY requested_at DESC LIMIT 1"
    );
    $injStmt->execute([$userId]);
    $lastInjection = $injStmt->fetchColumn();

    if ($lastInjection) {
      $elapsed   = time() - strtotime($lastInjection);
      $remaining = INJECTION_COOLDOWN_SECONDS - $elapsed;

      if ($remaining > 0) {
        self::logAbuse((string) $userId, 'prompt_injection', self::getClientIp(), $pdo);
        self::reject(429,
          "Your AI access is temporarily restricted due to a previous violation. Try again in {$remaining} seconds.",
          ['retry_after_seconds' => $remaining]
        );
      }
    }
    // ── End injection check ───────────────────────────────────────

    $cooldown = match($endpoint) {
      'chat'           => COOLDOWN_CHAT_SECONDS,
      'student_insights',
      'booking_recommendations',
      'admin_insights' => COOLDOWN_INSIGHTS_SECONDS,
      'report_summary' => COOLDOWN_SUMMARY_SECONDS,
      default          => 0,
    };

    if ($cooldown === 0) return;

    if ($role === 'admin') $cooldown = (int) floor($cooldown / 2);

    $stmt = $pdo->prepare(
      "SELECT requested_at FROM ai_usage_log
       WHERE user_id = ? AND endpoint = ? AND was_blocked = FALSE
       ORDER BY requested_at DESC LIMIT 1"
    );
    $stmt->execute([$userId, $endpoint]);
    $lastCall = $stmt->fetchColumn();

    if ($lastCall) {
      $elapsed   = time() - strtotime($lastCall);
      $remaining = $cooldown - $elapsed;

      if ($remaining > 0) {
        self::logUsage($userId, $role, $endpoint, $pdo, true, 'cooldown');
        self::reject(429,
          "Please wait {$remaining} seconds before using this feature again.",
          ['retry_after_seconds' => $remaining]
        );
      }
    }
  }

  private static function checkDailyQuota(
    string $endpoint, string $userId, string $role, PDO $pdo
  ): void {
    $quota = match(true) {
      $role === 'admin' && $endpoint === 'chat'             => QUOTA_ADMIN_CHAT_DAILY,
      $role === 'admin' && $endpoint === 'admin_insights'   => QUOTA_ADMIN_INSIGHTS_DAILY,
      $role === 'admin' && $endpoint === 'report_summary'   => QUOTA_ADMIN_SUMMARY_DAILY,
      $role === 'student' && $endpoint === 'chat'           => QUOTA_STUDENT_CHAT_DAILY,
      $role === 'student' && $endpoint === 'student_insights' => QUOTA_STUDENT_INSIGHTS_DAILY,
      $role === 'student' && $endpoint === 'booking_recommendations' => QUOTA_STUDENT_INSIGHTS_DAILY,
      default => 999,
    };

    $stmt = $pdo->prepare(
      "SELECT COUNT(*) FROM ai_usage_log
       WHERE user_id = ? AND endpoint = ?
         AND was_blocked = FALSE
         AND requested_at >= date_trunc('day', NOW())"
    );
    $stmt->execute([$userId, $endpoint]);
    $used = (int) $stmt->fetchColumn();

    if ($used >= $quota) {
      $remaining = 0;
      self::logUsage($userId, $role, $endpoint, $pdo, true, 'daily_quota');
      self::reject(429,
        "You've used all {$quota} AI requests for this feature today. Your quota resets at midnight.",
        [
          'used'       => $used,
          'quota'      => $quota,
          'remaining'  => $remaining,
          'resets_at'  => self::midnightTimestamp(),
        ]
      );
    }
  }

  private static function checkAbuseBlock(
    string $identifier, string $ip, PDO $pdo
  ): void {
    $windowStart = date('Y-m-d H:i:s', time() - ABUSE_FAILURE_WINDOW_SECONDS);
    $stmt = $pdo->prepare(
      "SELECT COUNT(*) FROM ai_abuse_log
       WHERE identifier = ? AND attempted_at >= ?"
    );
    $stmt->execute([$identifier, $windowStart]);
    $failures = (int) $stmt->fetchColumn();

    if ($failures >= ABUSE_FAILURE_THRESHOLD) {
      $stmt2 = $pdo->prepare(
        "SELECT attempted_at FROM ai_abuse_log
         WHERE identifier = ? ORDER BY attempted_at DESC LIMIT 1"
      );
      $stmt2->execute([$identifier]);
      $lastFailure  = $stmt2->fetchColumn();
      $blockExpires = strtotime($lastFailure) + ABUSE_BLOCK_DURATION_SECONDS;
      $retryAfter   = max(0, $blockExpires - time());

      self::reject(429,
        'Too many failed requests. Please try again later.',
        ['retry_after_seconds' => $retryAfter]
      );
    }
  }

  // ════════════════════════════════════════════════════════════
  // Logging helpers
  // ════════════════════════════════════════════════════════════

  public static function logUsage(
    string $userId, string $role, string $endpoint,
    PDO $pdo, bool $blocked = false, ?string $blockReason = null
  ): void {
    $stmt = $pdo->prepare(
      "INSERT INTO ai_usage_log (user_id, role, endpoint, was_blocked, block_reason)
       VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([$userId, $role, $endpoint, (int)$blocked, $blockReason]);

    if (!$blocked) {
      $col = match($endpoint) {
        'chat'             => 'chat_calls',
        'student_insights',
        'admin_insights'   => 'analysis_calls',
        'report_summary'   => 'summary_calls',
        default            => null,
      };
      if ($col) {
        $pdo->prepare(
          "UPDATE ai_global_budget SET {$col} = {$col} + 1
           WHERE budget_date = CURRENT_DATE"
        )->execute();
      }
    }
  }

  public static function logAbuse(
    string $identifier, string $failureType, string $ip, PDO $pdo
  ): void {
    try {
      $stmt = $pdo->prepare(
        "INSERT INTO ai_abuse_log (identifier, failure_type, ip_address)
         VALUES (?, ?, ?::inet)"
      );
      $stmt->execute([$identifier, $failureType, $ip]);
    } catch (\Exception $e) {
      // Never let abuse logging crash the response
    }
  }

  private static function touchSession(string $tokenHash, PDO $pdo): void {
    $pdo->prepare(
      "UPDATE user_sessions SET last_used_at = NOW() WHERE token_hash = ?"
    )->execute([$tokenHash]);
  }

  // ════════════════════════════════════════════════════════════
  // Utilities
  // ════════════════════════════════════════════════════════════

  private static function buildFingerprint(): string {
    $ua   = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    return hash('sha256', $ua . '|' . $lang);
  }

  public static function getClientIp(): string {
    foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
      if (!empty($_SERVER[$key])) {
        $ip = trim(explode(',', $_SERVER[$key])[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
      }
    }
    return '0.0.0.0';
  }

  private static function midnightTimestamp(): string {
    return date('Y-m-d 00:00:00', strtotime('tomorrow'));
  }

  private static function reject(int $httpCode, string $message, array $extra = []): never {
    http_response_code($httpCode);
    echo json_encode(array_merge(
      ['success' => false, 'message' => $message],
      $extra
    ));
    exit;
  }
}
