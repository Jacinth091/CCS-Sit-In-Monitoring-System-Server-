<?php

require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

requireAdmin();

$page = $_GET['page'] ?? 1;
$perPage = $_GET['per_page'] ?? 20;

// If no entity_type is provided, default to reservation-related ones for this specific endpoint
$entity_type = $_GET['entity_type'] ?? ['reservation', 'pc'];

$filters = [
    'event_types' => $_GET['event_types'] ?? [],
    'entity_type' => $entity_type,
    'date_from' => $_GET['date_from'] ?? null,
    'date_to' => $_GET['date_to'] ?? null
];

try {
    $auditLog = new AuditLog($db);
    $logs = $auditLog->getFiltered($filters, (int)$page, (int)$perPage);
    
    sendSuccess(200, "Audit log retrieved.", $logs, [
        'page' => (int)$page,
        'per_page' => (int)$perPage
    ]);
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
