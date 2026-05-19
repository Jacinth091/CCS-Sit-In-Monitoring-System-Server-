<?php
// api/lab/read.php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/initialize.php';

try {
    $labModel = new Laboratory($db);
    $labs = $labModel->getAllWithSoftware();
    sendSuccess(200, "Laboratories retrieved successfully", $labs);
} catch (Exception $e) {
    sendError(500, "An error occurred.", $e);
}
