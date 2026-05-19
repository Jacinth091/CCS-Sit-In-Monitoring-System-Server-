<?php

require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/initialize.php';

requireAuth();

$lab_id = $_GET['lab_id'] ?? null;

if (!$lab_id) {
    sendError(400, "Missing required parameter (lab_id).");
}

try {
    // Check if lab exists
    $stmt = $db->prepare("SELECT id, capacity FROM laboratories WHERE id = :id AND deleted_at IS NULL");
    $stmt->execute([':id' => $lab_id]);
    $lab = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lab) {
        sendError(404, "Laboratory not found.");
    }

    // Get PCs
    $stmt = $db->prepare("SELECT id, pc_number, pc_status, reservation_status FROM pcs WHERE lab_id = :lab_id");
    $stmt->execute([':lab_id' => $lab_id]);
    $pcs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If no PCs but capacity > 0, we could seed them like in admin, 
    // but usually admin should have done that.
    // For safety, let's just return what's there.

    sendSuccess(200, "Laboratory PCs retrieved.", $pcs);
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
