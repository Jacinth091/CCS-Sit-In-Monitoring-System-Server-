<?php

require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

requireAdmin();

$lab_id = $_GET['lab_id'] ?? null;

if (!$lab_id) {
    sendError(400, "Laboratory ID is required.");
}

try {
    $sitInModel = new SitIn($db);
    $resModel = new Reservation($db);

    // Get PCs currently in use
    $activePcs = $sitInModel->getOccupiedPcs((int)$lab_id);

    // Get PCs reserved for now
    $reservedPcs = $resModel->getReservationsNow((int)$lab_id);

    // Combine and unique
    $occupied = array_unique(array_merge($activePcs, $reservedPcs));
    
    // Convert to integers
    $occupied = array_map('intval', $occupied);
    
    sendSuccess(200, "Occupied PCs retrieved.", [
        'occupied' => array_values($occupied),
        'active' => array_map('intval', $activePcs),
        'reserved' => array_map('intval', $reservedPcs)
    ]);
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e->getMessage());
}
