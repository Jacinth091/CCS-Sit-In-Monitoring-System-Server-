<?php

class AiSecuritySeeder {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function run() {
        // Clear existing AI tables
        $this->db->exec("TRUNCATE TABLE user_sessions RESTART IDENTITY CASCADE");
        $this->db->exec("TRUNCATE TABLE ai_usage_log RESTART IDENTITY CASCADE");
        $this->db->exec("TRUNCATE TABLE ai_abuse_log RESTART IDENTITY CASCADE");
        $this->db->exec("TRUNCATE TABLE ai_global_budget RESTART IDENTITY CASCADE");
        $this->db->exec("TRUNCATE TABLE ai_report_cache RESTART IDENTITY CASCADE");

        // Fetch some students
        $students = $this->db->query("SELECT student_id FROM students LIMIT 5")->fetchAll(PDO::FETCH_COLUMN);
        if (empty($students)) {
            echo "  → Skipped: Run StudentSeeder first.\n";
            return;
        }

        $adminUsername = $_ENV['ADMIN_USERNAME'] ?? 'admin';
        $now = new DateTime();

        // 1. Seed user_sessions
        $sessions = [
            [
                'user_id' => $students[0],
                'role' => 'student',
                'token_hash' => hash('sha256', 'mock_student_token_1'),
                'device_fingerprint' => hash('sha256', 'Mozilla/5.0|en-US'),
                'ip_address' => '192.168.1.50',
                'created_at' => (clone $now)->modify('-2 hours')->format('Y-m-d H:i:s'),
                'expires_at' => (clone $now)->modify('+22 hours')->format('Y-m-d H:i:s'),
                'last_used_at' => (clone $now)->modify('-5 minutes')->format('Y-m-d H:i:s'),
                'is_active' => true,
            ],
            [
                'user_id' => $adminUsername,
                'role' => 'admin',
                'token_hash' => hash('sha256', 'mock_admin_token_1'),
                'device_fingerprint' => hash('sha256', 'Mozilla/5.0 Chrome/120.0|en-US'),
                'ip_address' => '192.168.1.100',
                'created_at' => (clone $now)->modify('-1 hour')->format('Y-m-d H:i:s'),
                'expires_at' => (clone $now)->modify('+23 hours')->format('Y-m-d H:i:s'),
                'last_used_at' => (clone $now)->modify('-2 minutes')->format('Y-m-d H:i:s'),
                'is_active' => true,
            ],
            [
                'user_id' => $students[1],
                'role' => 'student',
                'token_hash' => hash('sha256', 'mock_expired_token'),
                'device_fingerprint' => hash('sha256', 'Safari/605.1|en-US'),
                'ip_address' => '192.168.1.55',
                'created_at' => (clone $now)->modify('-2 days')->format('Y-m-d H:i:s'),
                'expires_at' => (clone $now)->modify('-1 day')->format('Y-m-d H:i:s'),
                'last_used_at' => (clone $now)->modify('-1 day')->format('Y-m-d H:i:s'),
                'is_active' => false,
            ]
        ];

        $sessionStmt = $this->db->prepare("
            INSERT INTO user_sessions (user_id, role, token_hash, device_fingerprint, ip_address, created_at, expires_at, last_used_at, is_active)
            VALUES (:user_id, :role, :token_hash, :device_fingerprint, :ip_address::inet, :created_at, :expires_at, :last_used_at, :is_active)
        ");

        foreach ($sessions as $s) {
            $sessionStmt->execute([
                ':user_id' => $s['user_id'],
                ':role' => $s['role'],
                ':token_hash' => $s['token_hash'],
                ':device_fingerprint' => $s['device_fingerprint'],
                ':ip_address' => $s['ip_address'],
                ':created_at' => $s['created_at'],
                ':expires_at' => $s['expires_at'],
                ':last_used_at' => $s['last_used_at'],
                ':is_active' => $s['is_active'] ? 1 : 0
            ]);
        }
        echo "  → " . count($sessions) . " AI user sessions seeded.\n";

        // 2. Seed ai_global_budget
        $budgets = [
            [
                'budget_date' => (clone $now)->modify('-2 days')->format('Y-m-d'),
                'chat_calls' => 150,
                'analysis_calls' => 45,
                'summary_calls' => 12
            ],
            [
                'budget_date' => (clone $now)->modify('-1 day')->format('Y-m-d'),
                'chat_calls' => 310,
                'analysis_calls' => 88,
                'summary_calls' => 24
            ],
            [
                'budget_date' => (clone $now)->format('Y-m-d'),
                'chat_calls' => 45,
                'analysis_calls' => 10,
                'summary_calls' => 3
            ]
        ];

        $budgetStmt = $this->db->prepare("
            INSERT INTO ai_global_budget (budget_date, chat_calls, analysis_calls, summary_calls)
            VALUES (:budget_date, :chat_calls, :analysis_calls, :summary_calls)
        ");

        foreach ($budgets as $b) {
            $budgetStmt->execute([
                ':budget_date' => $b['budget_date'],
                ':chat_calls' => $b['chat_calls'],
                ':analysis_calls' => $b['analysis_calls'],
                ':summary_calls' => $b['summary_calls']
            ]);
        }
        echo "  → " . count($budgets) . " AI daily budgets seeded.\n";

        // 3. Seed ai_usage_log
        $usageLogs = [];
        $endpoints = ['chat', 'student_insights', 'admin_insights', 'report_summary'];

        // Generate ~50 logs for the past 3 days
        for ($i = 0; $i < 50; $i++) {
            $dayOffset = rand(0, 2);
            $requestedAt = (clone $now)->modify("-$dayOffset days");
            $requestedAt->setTime(rand(8, 20), rand(0, 59), rand(0, 59));

            $role = rand(1, 10) > 7 ? 'admin' : 'student';
            $user_id = $role === 'admin' ? $adminUsername : $students[array_rand($students)];

            if ($role === 'admin') {
                $endpoint = rand(1, 2) === 1 ? 'admin_insights' : 'report_summary';
            } else {
                $endpoint = rand(1, 4) <= 3 ? 'chat' : 'student_insights';
            }

            $wasBlocked = rand(1, 100) <= 8; // 8% failure/block rate
            $blockReason = null;
            if ($wasBlocked) {
                $reasons = ['cooldown', 'daily_quota', 'burst_spam', 'prompt_injection'];
                $blockReason = $reasons[array_rand($reasons)];
            }

            $tokensUsed = $wasBlocked ? null : rand(150, 1500);

            $usageLogs[] = [
                'user_id' => $user_id,
                'role' => $role,
                'endpoint' => $endpoint,
                'tokens_used' => $tokensUsed,
                'requested_at' => $requestedAt->format('Y-m-d H:i:s'),
                'was_blocked' => $wasBlocked,
                'block_reason' => $blockReason
            ];
        }

        $usageStmt = $this->db->prepare("
            INSERT INTO ai_usage_log (user_id, role, endpoint, tokens_used, requested_at, was_blocked, block_reason)
            VALUES (:user_id, :role, :endpoint, :tokens_used, :requested_at, :was_blocked, :block_reason)
        ");

        foreach ($usageLogs as $log) {
            $usageStmt->execute([
                ':user_id' => $log['user_id'],
                ':role' => $log['role'],
                ':endpoint' => $log['endpoint'],
                ':tokens_used' => $log['tokens_used'],
                ':requested_at' => $log['requested_at'],
                ':was_blocked' => $log['was_blocked'] ? 1 : 0,
                ':block_reason' => $log['block_reason']
            ]);
        }
        echo "  → " . count($usageLogs) . " AI usage log entries seeded.\n";

        // 4. Seed ai_abuse_log
        $abuseLogs = [
            [
                'identifier' => '192.168.1.200',
                'failure_type' => 'invalid_signature',
                'ip_address' => '192.168.1.200',
                'attempted_at' => (clone $now)->modify('-4 hours')->format('Y-m-d H:i:s'),
            ],
            [
                'identifier' => $students[2],
                'failure_type' => 'prompt_injection',
                'ip_address' => '192.168.1.60',
                'attempted_at' => (clone $now)->modify('-1 hour')->format('Y-m-d H:i:s'),
            ],
            [
                'identifier' => 'unknown_token_hash',
                'failure_type' => 'invalid_token',
                'ip_address' => '10.0.0.12',
                'attempted_at' => (clone $now)->modify('-30 minutes')->format('Y-m-d H:i:s'),
            ],
        ];

        $abuseStmt = $this->db->prepare("
            INSERT INTO ai_abuse_log (identifier, failure_type, ip_address, attempted_at)
            VALUES (:identifier, :failure_type, :ip_address::inet, :attempted_at)
        ");

        foreach ($abuseLogs as $al) {
            $abuseStmt->execute([
                ':identifier' => $al['identifier'],
                ':failure_type' => $al['failure_type'],
                ':ip_address' => $al['ip_address'],
                ':attempted_at' => $al['attempted_at']
            ]);
        }
        echo "  → " . count($abuseLogs) . " AI abuse/block logs seeded.\n";

        // 5. Seed ai_report_cache
        $samplePayloads = [
            'report_summary' => [
                'summary' => "Yesterday showed peak laboratory usage between 1:00 PM and 4:00 PM, with LAB 526 reaching 95% capacity. The primary purpose was programming work. Web development lab followed closely with 70% capacity.",
                'total_hours' => 245.5,
                'active_students' => 45,
                'peak_lab' => "LAB 526",
                'peak_time' => "14:00 - 15:00"
            ],
            'admin_insights' => [
                'insights' => [
                    "High density warning in Cisco Networking Lab (LAB 525) on Wednesday afternoons.",
                    "Active Software Requests suggest that students require Flutter SDK and Blender to be installed on Multimedia Lab computers.",
                    "Testimonials show positive feedback trend (+15% rating increase) since new keyboards were provided."
                ],
                'utilization_rate' => "72.4%",
                'health_score' => "Excellent"
            ]
        ];

        $caches = [
            [
                'cache_key' => 'report_summary_yesterday',
                'report_type' => 'report_summary',
                'payload' => json_encode($samplePayloads['report_summary']),
                'generated_at' => (clone $now)->modify('-3 hours')->format('Y-m-d H:i:s'),
                'expires_at' => (clone $now)->modify('+1 hour')->format('Y-m-d H:i:s'),
                'model_used' => 'gemini-2.5-flash',
                'tokens_used' => 840,
                'hit_count' => 5,
                'data_fingerprint' => hash('sha256', 'yesterday_report_data_fingerprint')
            ],
            [
                'cache_key' => 'admin_insights_general',
                'report_type' => 'admin_insights',
                'payload' => json_encode($samplePayloads['admin_insights']),
                'generated_at' => (clone $now)->modify('-15 minutes')->format('Y-m-d H:i:s'),
                'expires_at' => (clone $now)->modify('+45 minutes')->format('Y-m-d H:i:s'),
                'model_used' => 'gemini-2.5-flash',
                'tokens_used' => 1250,
                'hit_count' => 12,
                'data_fingerprint' => hash('sha256', 'admin_insights_data_fingerprint')
            ]
        ];

        $cacheStmt = $this->db->prepare("
            INSERT INTO ai_report_cache (cache_key, report_type, payload, generated_at, expires_at, model_used, tokens_used, hit_count, data_fingerprint)
            VALUES (:cache_key, :report_type, :payload::jsonb, :generated_at, :expires_at, :model_used, :tokens_used, :hit_count, :data_fingerprint)
        ");

        foreach ($caches as $c) {
            $cacheStmt->execute([
                ':cache_key' => $c['cache_key'],
                ':report_type' => $c['report_type'],
                ':payload' => $c['payload'],
                ':generated_at' => $c['generated_at'],
                ':expires_at' => $c['expires_at'],
                ':model_used' => $c['model_used'],
                ':tokens_used' => $c['tokens_used'],
                ':hit_count' => $c['hit_count'],
                ':data_fingerprint' => $c['data_fingerprint']
            ]);
        }
        echo "  → " . count($caches) . " AI report cache summaries seeded.\n";
    }
}
