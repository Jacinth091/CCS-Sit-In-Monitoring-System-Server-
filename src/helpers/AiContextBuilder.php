<?php

/**
 * AiContextBuilder — builds live database context for AI system prompts.
 *
 * All queries use the actual CCS Sit-In Monitoring System schema.
 * Column names and table structures verified against the live models.
 */
class AiContextBuilder {

  private PDO $db;

  public function __construct(PDO $db) {
    $this->db = $db;
  }

  // ── Student context ────────────────────────────────────────

  /**
   * Full context payload for a student.
   * Called by chat.php (student role) and student_insights.php
   */
  public function forStudent(string $studentId): array {
    return [
      'profile'               => $this->getStudentProfile($studentId),
      'session_stats'         => $this->getStudentSessionStats($studentId),
      'recent_sessions'       => $this->getRecentSessions($studentId, 10),
      'upcoming_reservations' => $this->getUpcomingReservations($studentId),
      'lab_status'            => $this->getCurrentLabStatus(),
    ];
  }

  // ── Admin context ──────────────────────────────────────────

  /**
   * Full context payload for an admin.
   * Called by chat.php (admin role) and admin_insights.php
   */
  public function forAdmin(): array {
    return [
      'active_sessions_count'     => $this->getActiveSessionCount(),
      'active_sessions_list'      => $this->getActiveSessions(10), 
      'lab_status'                => $this->getCurrentLabStatus(),
      'pending_reservations_count' => $this->getPendingReservationCount(),
      'pending_reservations_list'  => $this->getPendingReservationsDetailed(10),
      'sessions_today'            => $this->getSessionCountToday(),
      'sessions_this_week'        => $this->getSessionCountThisWeek(),
      'sessions_last_week'        => $this->getSessionCountLastWeek(),
      'top_students_this_month'   => $this->getTopStudentsByHours(5),
      'reservation_stats_30d'     => $this->getReservationStats(),
      'lab_utilization'           => $this->getLabUtilizationThisWeek(),
      'purpose_distribution'      => $this->getPurposeDistribution(),
      'hourly_distribution'       => $this->getHourlyDistribution(),
      'avg_session_duration_min'  => $this->getAvgSessionDuration(),
    ];
  }

  // ── Report context ─────────────────────────────────────────

  /**
   * Wraps raw report rows with schema context for the summary endpoint.
   */
  public function forReport(array $reportRows): array {
    return [
      'report_rows'  => $reportRows,
      'row_count'    => count($reportRows),
      'generated_at' => date('Y-m-d H:i:s'),
      'schema_note'  => 'Each row: student_id, student_name, lab_name, lab_code, pc_number, date, time_in, time_out, duration_hours, duration_minutes, purpose, status',
    ];
  }

  // ── Schema description (injected into system prompts) ──────

  public static function schemaDescription(): string {
    return <<<SCHEMA
## Database Schema Summary

**students** — id (PK), student_id (string identifier), first_name, last_name, middle_name, course, course_level, email, address, session (remaining credits, default 30), profile_pic, is_active

**sit_in_logs** — id, student_id (varchar, FK to students.student_id), lab_id, pc_number, time_in (timestamp), time_out (timestamp), status (ongoing/completed), purpose, reservation_id, deleted_at

**reservations** — id, student_id, lab_id, pc_number, reserved_date, reserved_time, status (pending/approved/rejected/rescheduled/cancelled/fulfilled), purpose, admin_note

**laboratories** — id, name, lab_code, capacity, is_active, image_path

**software** — id, name, description, version, icon_path (joined via lab_software junction table)

**testimonials** — id, student_id, rating, content, status (pending/approved/rejected)

**feedback** — id, sit_in_id, rating, comment (student feedback on sessions)
SCHEMA;
  }

  // ── Private query methods ──────────────────────────────────

  private function getPendingReservationsDetailed(int $limit): array {
    try {
      $stmt = $this->db->prepare(
        "SELECT s.first_name || ' ' || s.last_name AS student_name,
                s.student_id, l.name AS lab_name, l.lab_code,
                r.pc_number, r.reserved_date, r.reserved_time, r.purpose, r.id AS reservation_id
         FROM reservations r
         JOIN students s ON s.student_id = r.student_id
         JOIN laboratories l ON l.id = r.lab_id
         WHERE r.status = 'pending' AND r.deleted_at IS NULL
         ORDER BY r.reserved_date, r.reserved_time LIMIT ?"
      );
      $stmt->execute([$limit]);
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Exception $e) {
      return [];
    }
  }

  private function getActiveSessions(int $limit): array {
    try {
      $stmt = $this->db->prepare(
        "SELECT s.first_name || ' ' || s.last_name AS student_name,
                s.student_id, l.name AS lab_name, l.lab_code,
                sl.pc_number, sl.time_in, sl.purpose
         FROM sit_in_logs sl
         JOIN students s ON s.student_id = sl.student_id
         JOIN laboratories l ON l.id = sl.lab_id
         WHERE sl.status = 'ongoing' AND sl.deleted_at IS NULL
         ORDER BY sl.time_in DESC LIMIT ?"
      );
      $stmt->execute([$limit]);
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Exception $e) {
      return [];
    }
  }

  private function getStudentProfile(string $studentId): array {
    $stmt = $this->db->prepare(
      "SELECT id, student_id, first_name, last_name, course, course_level, session
       FROM students WHERE student_id = ? LIMIT 1"
    );
    $stmt->execute([$studentId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
  }

  private function getStudentSessionStats(string $studentId): array {
    $stmt = $this->db->prepare(
      "SELECT
         COUNT(*)                                                              AS total_sessions,
         COALESCE(SUM(EXTRACT(EPOCH FROM (time_out - time_in)) / 60), 0)      AS total_minutes,
         COALESCE(AVG(EXTRACT(EPOCH FROM (time_out - time_in)) / 60), 0)      AS avg_minutes,
         COALESCE(MAX(EXTRACT(EPOCH FROM (time_out - time_in)) / 60), 0)      AS longest_minutes,
         MAX(time_in::date)                                                    AS last_session_date
       FROM sit_in_logs
       WHERE student_id = ? AND status = 'completed' AND time_out IS NOT NULL AND deleted_at IS NULL"
    );
    $stmt->execute([$studentId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
  }

  private function getRecentSessions(string $studentId, int $limit): array {
    $stmt = $this->db->prepare(
      "SELECT l.name AS lab_name, l.lab_code, s.pc_number, s.time_in,
              s.time_out, s.purpose,
              ROUND(EXTRACT(EPOCH FROM (s.time_out - s.time_in)) / 60) AS duration_minutes
       FROM sit_in_logs s
       JOIN laboratories l ON l.id = s.lab_id
       WHERE s.student_id = ? AND s.status = 'completed' AND s.time_out IS NOT NULL AND s.deleted_at IS NULL
       ORDER BY s.time_in DESC LIMIT ?"
    );
    $stmt->execute([$studentId, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  private function getUpcomingReservations(string $studentId): array {
    $stmt = $this->db->prepare(
      "SELECT l.name AS lab_name, l.lab_code, r.pc_number, r.reserved_date,
              r.reserved_time, r.status
       FROM reservations r
       JOIN laboratories l ON l.id = r.lab_id
       WHERE r.student_id = ? AND r.reserved_date >= CURRENT_DATE
         AND r.status IN ('pending','approved','rescheduled')
         AND r.deleted_at IS NULL
       ORDER BY r.reserved_date, r.reserved_time LIMIT 5"
    );
    $stmt->execute([$studentId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  private function getCurrentLabStatus(): array {
    try {
      $stmt = $this->db->query(
        "SELECT name, lab_code, capacity, is_active FROM laboratories
         WHERE deleted_at IS NULL ORDER BY name"
      );
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Exception $e) {
      return [];
    }
  }

  private function getActiveSessionCount(): int {
    try {
      $stmt = $this->db->query(
        "SELECT COUNT(*) FROM sit_in_logs WHERE status = 'ongoing' AND deleted_at IS NULL"
      );
      return (int) $stmt->fetchColumn();
    } catch (\Exception $e) {
      return 0;
    }
  }

  private function getPendingReservationCount(): int {
    try {
      $stmt = $this->db->query(
        "SELECT COUNT(*) FROM reservations WHERE status = 'pending' AND deleted_at IS NULL"
      );
      return (int) $stmt->fetchColumn();
    } catch (\Exception $e) {
      return 0;
    }
  }

  private function getSessionCountToday(): int {
    try {
      $stmt = $this->db->query(
        "SELECT COUNT(*) FROM sit_in_logs WHERE time_in::date = CURRENT_DATE AND deleted_at IS NULL"
      );
      return (int) $stmt->fetchColumn();
    } catch (\Exception $e) {
      return 0;
    }
  }

  private function getSessionCountThisWeek(): int {
    try {
      $stmt = $this->db->query(
        "SELECT COUNT(*) FROM sit_in_logs
         WHERE time_in >= date_trunc('week', NOW()) AND deleted_at IS NULL"
      );
      return (int) $stmt->fetchColumn();
    } catch (\Exception $e) {
      return 0;
    }
  }

  private function getTopStudentsByHours(int $limit): array {
    try {
      $stmt = $this->db->prepare(
        "SELECT s.first_name || ' ' || LEFT(s.last_name, 1) || '.' AS name,
                ROUND(SUM(EXTRACT(EPOCH FROM (l.time_out - l.time_in)) / 60)) AS total_minutes
         FROM sit_in_logs l
         JOIN students s ON s.student_id = l.student_id
         WHERE l.time_in >= date_trunc('month', NOW())
           AND l.status = 'completed' AND l.time_out IS NOT NULL AND l.deleted_at IS NULL
         GROUP BY s.id, s.first_name, s.last_name
         ORDER BY total_minutes DESC LIMIT ?"
      );
      $stmt->execute([$limit]);
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Exception $e) {
      return [];
    }
  }

  private function getReservationStats(): array {
    try {
      $stmt = $this->db->query(
        "SELECT
           COUNT(*) FILTER (WHERE status = 'approved')    AS approved_count,
           COUNT(*) FILTER (WHERE status = 'rejected')    AS rejected_count,
           COUNT(*) FILTER (WHERE status = 'pending')     AS pending_count,
           COUNT(*) FILTER (WHERE status = 'cancelled')   AS cancelled_count
         FROM reservations
         WHERE reserved_date >= CURRENT_DATE - INTERVAL '30 days'
           AND deleted_at IS NULL"
      );
      return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (\Exception $e) {
      return [];
    }
  }

  private function getLabUtilizationThisWeek(): array {
    try {
      $stmt = $this->db->query(
        "SELECT l.name AS lab_name, l.lab_code,
                COUNT(s.id) AS session_count,
                COALESCE(ROUND(SUM(EXTRACT(EPOCH FROM (s.time_out - s.time_in)) / 60)), 0) AS total_minutes
         FROM laboratories l
         LEFT JOIN sit_in_logs s ON s.lab_id = l.id
           AND s.time_in >= date_trunc('week', NOW())
           AND s.deleted_at IS NULL
         WHERE l.deleted_at IS NULL
         GROUP BY l.id, l.name, l.lab_code
         ORDER BY session_count DESC"
      );
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Exception $e) {
      return [];
    }
  }

  private function getSessionCountLastWeek(): int {
    try {
      // Compare apples-to-apples: same number of days into last week
      // e.g. if today is Wed (3 days into the week), compare Mon-Wed this week vs Mon-Wed last week
      $stmt = $this->db->query(
        "SELECT COUNT(*) FROM sit_in_logs
         WHERE time_in >= date_trunc('week', NOW()) - INTERVAL '7 days'
           AND time_in < date_trunc('week', NOW()) - INTERVAL '7 days' + (NOW() - date_trunc('week', NOW()))
           AND deleted_at IS NULL"
      );
      return (int) $stmt->fetchColumn();
    } catch (\Exception $e) {
      return 0;
    }
  }

  private function getPurposeDistribution(): array {
    try {
      $stmt = $this->db->query(
        "SELECT COALESCE(NULLIF(TRIM(purpose), ''), 'Unknown') AS purpose,
                COUNT(*) AS count
         FROM sit_in_logs
         WHERE time_in >= CURRENT_DATE - INTERVAL '30 days' AND deleted_at IS NULL
         GROUP BY purpose ORDER BY count DESC LIMIT 8"
      );
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Exception $e) {
      return [];
    }
  }

  private function getHourlyDistribution(): array {
    try {
      $stmt = $this->db->query(
        "SELECT EXTRACT(HOUR FROM time_in)::int AS hour, COUNT(*) AS count
         FROM sit_in_logs
         WHERE time_in >= CURRENT_DATE - INTERVAL '14 days' AND deleted_at IS NULL
         GROUP BY hour ORDER BY count DESC LIMIT 6"
      );
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Exception $e) {
      return [];
    }
  }

  private function getAvgSessionDuration(): float {
    try {
      $stmt = $this->db->query(
        "SELECT COALESCE(AVG(EXTRACT(EPOCH FROM (time_out - time_in)) / 60), 0)
         FROM sit_in_logs
         WHERE time_out IS NOT NULL AND deleted_at IS NULL
           AND time_in >= CURRENT_DATE - INTERVAL '30 days'"
      );
      return round((float) $stmt->fetchColumn(), 1);
    } catch (\Exception $e) {
      return 0.0;
    }
  }
}
