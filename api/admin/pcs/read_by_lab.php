<?php

require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

requireAuth();

$lab_id = $_GET['lab_id'] ?? null;

if (!$lab_id) {
    sendError(400, "Missing required parameter (lab_id).");
}

try {
    // 1. Get lab details
    $stmt = $db->prepare("SELECT id, capacity, name FROM laboratories WHERE id = :id AND deleted_at IS NULL");
    $stmt->execute([':id' => $lab_id]);
    $lab = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lab) {
        sendError(404, "Laboratory not found.");
    }

    $capacity = (int)$lab['capacity'];
    
    // 2. Get PC operational/booking states from 'pcs' table
    $stmt = $db->prepare("SELECT * FROM pcs WHERE lab_id = :lab_id");
    $stmt->execute([':lab_id' => $lab['id']]);
    $db_pcs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $pcs_map = [];
    foreach ($db_pcs as $p) {
        $pcs_map[$p['pc_number']] = $p;
    }

    // 3. Get currently occupied PCs from sit-in logs
    $sitInModel = new SitIn($db);
    $activeOccupied = $sitInModel->getOccupiedPcs((int)$lab['id']);
    
    // 4. Get upcoming/current reservations
    $resModel = new Reservation($db);
    $reservedPcs = $resModel->getReservationsNow((int)$lab['id']);

    $results = [];
    
    // Seed PCs if this lab has 0 PCs (new lab)
    if (count($db_pcs) === 0 && $capacity > 0) {
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("
                INSERT INTO pcs (lab_id, pc_number, pc_status, reservation_status, updated_at)
                VALUES (:lab_id, :pc_number, 'active', 'open', NOW())
                ON CONFLICT DO NOTHING
                RETURNING *
            ");
            for ($i = 1; $i <= $capacity; $i++) {
                $stmt->execute([':lab_id' => $lab['id'], ':pc_number' => (string)$i]);
                $new_pc = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($new_pc) {
                    $db_pcs[] = $new_pc;
                }
            }
            $db->commit();
            
            // If RETURNING * didn't work for ON CONFLICT DO NOTHING, re-fetch
            if (count($db_pcs) === 0) {
                $stmt = $db->prepare("SELECT * FROM pcs WHERE lab_id = :lab_id");
                $stmt->execute([':lab_id' => $lab['id']]);
                $db_pcs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    foreach ($db_pcs as $pc_data) {
        $pc_status = $pc_data['pc_status'];
        $reservation_status = $pc_data['reservation_status'];
        $pc_number = $pc_data['pc_number'];

        // Business Logic: Override reservation status display
        $display_res_status = $reservation_status;
        
        if ($pc_status === 'disabled' || $pc_status === 'under maintenance') {
            $display_res_status = 'unavailable';
        } elseif (in_array((string)$pc_number, $activeOccupied) || in_array((string)$pc_number, $reservedPcs)) {
            $display_res_status = 'occupied';
        }

        $results[] = [
            'id' => $pc_data['id'],
            'pc_number' => $pc_number,
            'pc_status' => $pc_status,
            'reservation_status' => $display_res_status,
            'lab_id' => $lab['id'],
            'name' => $lab['name']
        ];
    }

    sendSuccess(200, "PCs for lab retrieved.", $results);
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
