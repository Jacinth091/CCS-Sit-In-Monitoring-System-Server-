<?php

class AiCache {
    /**
     * Retrieve cached payload by key if it is not expired.
     */
    public static function get(PDO $db, string $cacheKey): ?array {
        try {
            $stmt = $db->prepare("
                SELECT payload, expires_at, generated_at, data_fingerprint 
                FROM ai_report_cache 
                WHERE cache_key = :key 
                  AND expires_at > NOW() 
                LIMIT 1
            ");
            $stmt->execute([':key' => $cacheKey]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return null;
            }

            // Increment hit counter in a non-blocking update
            $updateStmt = $db->prepare("
                UPDATE ai_report_cache 
                SET hit_count = hit_count + 1 
                WHERE cache_key = :key
            ");
            $updateStmt->execute([':key' => $cacheKey]);

            $payload = json_decode($row['payload'], true);
            if (is_array($payload)) {
                $payload['_generated_at'] = $row['generated_at'];
                $payload['_cached'] = true;
            }
            return $payload;
        } catch (Exception $e) {
            error_log("AiCache::get error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Store or update a cached payload.
     */
    public static function set(
        PDO    $db,
        string $cacheKey,
        string $reportType,
        array  $payload,
        float  $ttlHours,
        string $modelUsed     = '',
        int    $tokensUsed    = 0,
        string $fingerprint   = ''
    ): void {
        try {
            // To ensure compatibility and absolute precision, pass the TTL in seconds
            $ttlSeconds = (int)round($ttlHours * 3600);

            $stmt = $db->prepare("
                INSERT INTO ai_report_cache 
                    (cache_key, report_type, payload, expires_at, model_used, tokens_used, data_fingerprint)
                VALUES 
                    (:key, :type, :payload, NOW() + (:ttl_seconds * INTERVAL '1 second'), :model, :tokens, :fingerprint)
                ON CONFLICT (cache_key) DO UPDATE SET
                    payload          = EXCLUDED.payload,
                    generated_at     = NOW(),
                    expires_at       = EXCLUDED.expires_at,
                    model_used       = EXCLUDED.model_used,
                    tokens_used      = EXCLUDED.tokens_used,
                    data_fingerprint = EXCLUDED.data_fingerprint,
                    hit_count        = 0
            ");
            
            $stmt->execute([
                ':key'         => $cacheKey,
                ':type'        => $reportType,
                ':payload'     => json_encode($payload),
                ':ttl_seconds' => $ttlSeconds,
                ':model'       => $modelUsed,
                ':tokens'      => $tokensUsed,
                ':fingerprint' => $fingerprint
            ]);
        } catch (Exception $e) {
            error_log("AiCache::set error: " . $e->getMessage());
        }
    }

    /**
     * Check if the cached data is still fresh by comparing fingerprints.
     * Returns the cached payload if fingerprint matches (data unchanged).
     * Returns null if fingerprint differs (data changed, needs regeneration).
     */
    public static function checkFreshness(PDO $db, string $cacheKey, string $currentFingerprint): ?array {
        try {
            $stmt = $db->prepare("
                SELECT payload, generated_at, data_fingerprint
                FROM ai_report_cache 
                WHERE cache_key = :key 
                LIMIT 1
            ");
            $stmt->execute([':key' => $cacheKey]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row || empty($row['data_fingerprint'])) {
                return null; // No cache or no fingerprint — must regenerate
            }

            if ($row['data_fingerprint'] === $currentFingerprint) {
                // Data hasn't changed — extend TTL and return cached payload
                $payload = json_decode($row['payload'], true);
                if (is_array($payload)) {
                    $payload['_generated_at'] = $row['generated_at'];
                    $payload['_cached'] = true;
                    $payload['_data_unchanged'] = true;
                }
                return $payload;
            }

            return null; // Fingerprint mismatch — data changed, must regenerate
        } catch (Exception $e) {
            error_log("AiCache::checkFreshness error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Compute a fingerprint from an associative array of key metrics.
     */
    public static function fingerprint(array $metrics): string {
        // Sort keys for deterministic hashing
        ksort($metrics);
        return md5(json_encode($metrics));
    }

    /**
     * Explicitly invalidate a cache key (e.g., when data changes).
     */
    public static function invalidate(PDO $db, string $cacheKey): void {
        try {
            $stmt = $db->prepare("DELETE FROM ai_report_cache WHERE cache_key = :key");
            $stmt->execute([':key' => $cacheKey]);
        } catch (Exception $e) {
            error_log("AiCache::invalidate error: " . $e->getMessage());
        }
    }
}
