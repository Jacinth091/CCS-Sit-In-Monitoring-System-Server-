<?php
require_once '../../../includes/cors.php';
require_once '../../../includes/initialize.php';

requireAdmin();

try {
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (empty($data['reservation_id'])) {
        sendError(400, "Reservation ID is required.");
    }

    $reservationId = $data['reservation_id'];
    
    // We instantiate Reservation model
    $reservationModel = new Reservation($db);
    
    $sitInId = $reservationModel->convertToSitIn($reservationId);

    sendSuccess(200, "Sit-in session started.", ['sitin_id' => $sitInId]);

} catch (Exception $e) {
    $code = $e->getCode() ?: 500;
    // ensure HTTP status codes
    if ($code < 400 || $code > 599) {
        $code = 500;
    }
    sendError($code, $e->getMessage());
}
