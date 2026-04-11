<?php

require_once '../../includes/cors.php';
require_once '../../includes/initialize.php';

requireAdmin();

try {
    $sitIn = new SitIn($db);
    $stmt = $sitIn->getAllRecords();
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($records as &$rec) {
        $rec['profile_pic'] = $rec['profile_pic'] ?? '';
    }

    sendSuccess(200, 'Sit-in records retrieved.', $records);

} catch (Exception $e) {
    sendError(500, 'Failed to fetch records.', $e);
}