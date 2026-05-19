<?php

require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

$admin = requireAdmin();

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['id']) || !isset($data['is_approved'])) {
    sendError(400, "ID and Approval status are required.");
}

try {
    $testModel = new Testimonial($db);
    
    // Fetch student_id for notification
    $stmt = $db->prepare("SELECT student_id FROM testimonials WHERE id = :id");
    $stmt->execute([':id' => $data['id']]);
    $studentId = $stmt->fetchColumn();

    if ($testModel->updateStatus($data['id'], $data['is_approved'])) {
        $status = $data['is_approved'] ? "approved" : "disapproved";
        
        if ($studentId) {
            create_notification(
                $db,
                $studentId,
                $admin->student_id,
                'testimonial',
                'Testimonial ' . ucfirst($status),
                "Your testimonial has been $status by the admin.",
                $data['id'],
                'testimonial'
            );
        }

        sendSuccess(200, "Testimonial $status successfully.");
    } else {
        sendError(500, "Failed to update testimonial status.");
    }
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
