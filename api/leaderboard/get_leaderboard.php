<?php
require_once '../../includes/cors.php';
require_once '../../includes/initialize.php';

// Public endpoint, no authentication required

try {
    $metric = isset($_GET['metric']) && in_array($_GET['metric'], ['hours', 'sessions']) ? $_GET['metric'] : 'hours';
    $period = isset($_GET['period']) && in_array($_GET['period'], ['weekly', 'monthly', 'all']) ? $_GET['period'] : 'monthly';
    
    // Note: requires the Leaderboard model to be loaded. 
    // Usually initialize.php loads models, or we do it explicitly.
    require_once __DIR__ . '/../../src/models/Leaderboard.php';
    
    $leaderboardModel = new Leaderboard($db);
    
    $entries = $leaderboardModel->getTopStudents($metric, $period, 20);

    $responseData = [
        'period' => $period,
        'metric' => $metric,
        'generated_at' => date('Y-m-d\TH:i:s'),
        'entries' => $entries
    ];

    sendSuccess(200, "Leaderboard fetched successfully", $responseData);

} catch (Exception $e) {
    sendError(500, "Failed to load leaderboard: " . $e->getMessage());
}
