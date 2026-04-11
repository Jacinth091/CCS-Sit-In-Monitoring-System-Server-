<?php
require_once '../../includes/cors.php'; 
require_once '../../includes/initialize.php';

try {
    $announcement = new Announcement($db);
    $data = $announcement->read();
    sendSuccess(200, 'Announcements retrieved.', $data);
} catch(Exception $e) {
    sendError(500, 'Database error occurred while fetching announcements.', $e);
}
