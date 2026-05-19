<?php
/**
 * GET /api/admin/reservations/read_all.php
 * Purpose: Fetch all reservations with optional status filtering for the Admin.
 */

require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

requireAdmin();

$status = $_GET['status'] ?? null;

try {
    $reservation = new Reservation($db);
    $results = $reservation->getAll(['status' => $status]);

    sendSuccess(200, "Reservations retrieved successfully.", $results);
} catch (Exception $e) {
    sendError(500, "Failed to retrieve reservations.", $e);
}
