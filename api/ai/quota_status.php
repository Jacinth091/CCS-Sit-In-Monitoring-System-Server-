<?php
require_once '../../includes/cors.php';
require_once '../../includes/initialize.php';
require_once '../../config/ai_limits.php';

// Auth - validate JWT
$currentUser = requireAuth();
$userId = (string) $currentUser->id;
$role = $currentUser->role;

$endpoints = ['chat', 'student_insights', 'booking_recommendations', 'admin_insights', 'report_summary'];
$result    = [];

foreach ($endpoints as $ep) {
  $quota = match(true) {
    $role === 'admin'   && $ep === 'chat'             => QUOTA_ADMIN_CHAT_DAILY,
    $role === 'admin'   && $ep === 'admin_insights'   => QUOTA_ADMIN_INSIGHTS_DAILY,
    $role === 'admin'   && $ep === 'report_summary'   => QUOTA_ADMIN_SUMMARY_DAILY,
    $role === 'student' && $ep === 'chat'             => QUOTA_STUDENT_CHAT_DAILY,
    $role === 'student' && $ep === 'student_insights' => QUOTA_STUDENT_INSIGHTS_DAILY,
    $role === 'student' && $ep === 'booking_recommendations' => QUOTA_STUDENT_INSIGHTS_DAILY,
    default => null,
  };

  if ($quota === null) continue; // not applicable for this role

  $stmt = $db->prepare(
    "SELECT COUNT(*) FROM ai_usage_log
     WHERE user_id = ? AND endpoint = ?
       AND was_blocked = FALSE
       AND requested_at >= date_trunc('day', NOW())"
  );
  $stmt->execute([$userId, $ep]);
  $used = (int) $stmt->fetchColumn();

  // Cooldown remaining
  $cooldown = match($ep) {
    'chat'           => COOLDOWN_CHAT_SECONDS,
    'student_insights',
    'booking_recommendations',
    'admin_insights' => COOLDOWN_INSIGHTS_SECONDS,
    'report_summary' => COOLDOWN_SUMMARY_SECONDS,
    default          => 0,
  };
  if ($role === 'admin') $cooldown = (int) floor($cooldown / 2);

  $cooldownRemaining = 0;
  if ($cooldown > 0) {
    $stmt2 = $db->prepare(
      "SELECT requested_at FROM ai_usage_log
       WHERE user_id = ? AND endpoint = ? AND was_blocked = FALSE
       ORDER BY requested_at DESC LIMIT 1"
    );
    $stmt2->execute([$userId, $ep]);
    $lastCall = $stmt2->fetchColumn();
    if ($lastCall) {
      $cooldownRemaining = max(0, $cooldown - (time() - strtotime($lastCall)));
    }
  }

  $result[$ep] = [
    'used'               => $used,
    'quota'              => $quota,
    'remaining'          => max(0, $quota - $used),
    'cooldown_remaining' => $cooldownRemaining,
    'resets_at'          => date('Y-m-d 00:00:00', strtotime('tomorrow')),
  ];
}

sendSuccess(200, 'Quota status retrieved successfully', $result);
