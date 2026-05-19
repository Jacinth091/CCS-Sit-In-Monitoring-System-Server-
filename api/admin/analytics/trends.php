<?php

require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

requireAdmin();

$from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$to   = $_GET['to']   ?? date('Y-m-d');
$days = $_GET['days'] ?? 30;

try {
    $dashboard = new Dashboard($db);
    
    $data = [
        'byPurpose'  => $dashboard->getSessionsByPurpose($from, $to),
        'byLab'      => $dashboard->getSessionsByLab($from, $to),
        'dailyTrend' => $dashboard->getDailyTrend($days),
        'peakHours'  => $dashboard->getPeakHours($from, $to),
        
        // Match extended trends expectations
        'hourly_distribution' => $dashboard->getPeakHours($from, $to),
        'course_breakdown'    => $dashboard->getSessionsByCourse($from, $to),
        'daily_sessions'      => $dashboard->getDailyTrend($days)
    ];

    sendSuccess(200, "Analytics data retrieved.", $data);

} catch (Exception $e) {
    sendError(500, "Failed to retrieve analytics data", $e);
}
