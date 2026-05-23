<?php
require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

$userData = requireAdmin();

try {
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : null;
    $per_page = isset($_GET['per_page']) ? min(100, max(1, (int)$_GET['per_page'])) : 10;
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;

    $swModel = new Software($db);
    $software = $swModel->getAll($page, $per_page, $search);
    sendSuccess(200, "Software retrieved successfully", $software);
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
