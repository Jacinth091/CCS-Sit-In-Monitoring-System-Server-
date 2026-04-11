<?php
// includes/notifications.php

/**
 * Reusable function to create notification records.
 */
function create_notification(
    PDO $db,
    ?string $student_id,
    ?string $admin_username,
    string $type,          // 'sit_in' | 'feedback' | 'system' | 'announcement'
    string $title,
    string $message,
    ?string $reference_id = null,
    ?string $reference_type = null
): void {
    $stmt = $db->prepare("
        INSERT INTO notifications 
            (student_id, admin_username, type, title, message, reference_id, reference_type)
        VALUES 
            (:student_id, :admin_username, :type, :title, :message, :reference_id, :reference_type)
    ");
    
    $stmt->execute([
        ':student_id'     => $student_id,
        ':admin_username' => $admin_username,
        ':type'           => $type,
        ':title'          => $title,
        ':message'        => $message,
        ':reference_id'   => $reference_id,
        ':reference_type' => $reference_type,
    ]);
}

/**
 * When publishing a new announcement — notify all active students.
 */
function notify_all_students(
    PDO $db,
    string $type,
    string $title,
    string $message,
    ?string $ref_id = null,
    ?string $ref_type = null,
    ?string $admin_username = 'admin'
): void {
    // 1. Get all active student_ids
    $stmt = $db->query("SELECT student_id FROM students WHERE is_active = TRUE AND deleted_at IS NULL");
    $student_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // 2. Prepare the notification insert
    $insertStmt = $db->prepare("
        INSERT INTO notifications (student_id, admin_username, type, title, message, reference_id, reference_type)
        VALUES (:student_id, :admin_username, :type, :title, :message, :reference_id, :reference_type)
    ");

    foreach ($student_ids as $sid) {
        $insertStmt->execute([
            ':student_id'     => $sid,
            ':admin_username' => $admin_username,
            ':type'           => $type,
            ':title'          => $title,
            ':message'        => $message,
            ':reference_id'   => $ref_id,
            ':reference_type' => $ref_type,
        ]);
    }
}
