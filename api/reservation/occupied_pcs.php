<?php

require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/initialize.php';

requireAuth();

$lab_id = $_GET['lab_id'] ?? null;
$date = $_GET['date'] ?? null;
$time_slot = $_GET['time_slot'] ?? null;

if (!$lab_id || !$date || !$time_slot) {
    sendError(400, "Missing required parameters (lab_id, date, time_slot).");
}

// Basic format validation
if (!is_numeric($lab_id)) {
    sendError(400, "Invalid laboratory ID.");
}

$date_obj = DateTime::createFromFormat('Y-m-d', $date);
if (!$date_obj || $date_obj->format('Y-m-d') !== $date) {
    sendError(400, "Invalid date format. Use YYYY-MM-DD.");
}

try {
    $resModel = new Reservation($db);
    $sitInModel = new SitIn($db);

    // 1. Get PCs reserved for this date (day-level blocking)
    $reserved = $resModel->getOccupiedPcs((int)$lab_id, $date, $time_slot);

    // 2. If the date is TODAY, also check for active sit-in sessions
    $active = [];
    if ($date === date('Y-m-d')) {
        $active = $sitInModel->getOccupiedPcs((int)$lab_id);
    }

    // 3. Admin-reserved PCs (for transparency)
    $stmt = $db->prepare("
        SELECT pc_number FROM pcs 
        WHERE lab_id = :lab_id 
        AND reservation_status = 'reserved'
        AND pc_status = 'active'
    ");
    $stmt->execute([':lab_id' => $lab_id]);
    $adminReserved = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // 4. Unavailable PCs (disabled/maintenance)
    $stmt = $db->prepare("
        SELECT pc_number FROM pcs 
        WHERE lab_id = :lab_id 
        AND pc_status != 'active'
    ");
    $stmt->execute([':lab_id' => $lab_id]);
    $unavailable = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $reserved = array_unique(array_merge($reserved, $adminReserved));

    $reserved = array_map('intval', array_values($reserved));
    $active = array_map('intval', array_values($active));
    $unavailable = array_map('intval', array_values($unavailable));
    
    sendSuccess(200, "PC availability retrieved.", [
        'reserved' => $reserved,
        'occupied' => $active,
        'unavailable' => $unavailable
    ]);
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
