<?php

require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/initialize.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdmin();
} else {
    requireAuth();
}

try {
    $resModel = new Reservation($db);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents("php://input"), true);
        if (!isset($data['enabled'])) {
            sendError(400, "Enabled status is required.");
        }
        if ($resModel->setReservationsEnabled((bool)$data['enabled'])) {
            sendSuccess(200, "Reservation settings updated.");
        } else {
            sendError(500, "Failed to update settings.");
        }
    } else {
        // GET
        $enabled = $resModel->isReservationsEnabled();
        sendSuccess(200, "Reservation settings retrieved.", ['enabled' => $enabled]);
    }

} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
