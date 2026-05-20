<?php
/**
 * GET /api/admin/reservations/read_all.php
 * Purpose: Fetch all reservations with optional status filtering for the Admin.
 */

require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

requireAdmin();

$status = $_GET['status'] ?? null;
$lab_id = $_GET['lab_id'] ?? null;

// Fallback to request body if lab_id is not in URL
if (!$lab_id) {
    $data = json_decode(file_get_contents("php://input"), true);
    if (isset($data['lab_id'])) {
        $lab_id = $data['lab_id'];
    }
}

// Debugging: Log incoming parameters
error_log("read_all.php received: status=" . var_export($status, true) . ", lab_id=" . var_export($lab_id, true));

try {
    $reservation = new Reservation($db);
    $results = $reservation->getAll(['status' => $status, 'lab_id' => $lab_id]);

    sendSuccess(200, "Reservations retrieved successfully.", $results);
} catch (Exception $e) {
    sendError(500, "Failed to retrieve reservations.", $e);
}
