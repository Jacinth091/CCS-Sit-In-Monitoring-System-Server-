<?php

require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

requireAdmin();

try {
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : null;
    $per_page = isset($_GET['per_page']) ? min(100, max(1, (int)$_GET['per_page'])) : 10;
    $status = isset($_GET['status']) ? trim($_GET['status']) : null;
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;

    $testModel = new Testimonial($db);

    if ($page === null) {
        $data = $testModel->read(true, null, null, $status, $search);
        sendSuccess(200, "Testimonials retrieved.", $data);
    } else {
        $res = $testModel->read(true, $page, $per_page, $status, $search);
        sendSuccess(200, "Testimonials retrieved.", [
            'records' => $res['records'],
            'meta' => [
                'total' => $res['total'],
                'page' => $page,
                'per_page' => $per_page,
                'last_page' => ceil($res['total'] / $per_page)
            ]
        ]);
    }
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
