<?php
require_once '../../includes/cors.php'; 
require_once '../../includes/initialize.php';

try {
    $query = "SELECT id, title, content as body, to_char(created_at, 'YYYY-Mon-DD') as date FROM announcements ORDER BY created_at DESC";
    
    // Note: If using PostgreSQL, to_char translates well. If MySQL, use DATE_FORMAT(created_at, '%Y-%b-%d')
    // I will use standard SQL to avoid syntax errors and format in PHP to be safe across DB types.
    
    $query = "SELECT id, title, content as body, created_at FROM announcements ORDER BY created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $announcements = array();
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Format date: 'YYYY-Mon-DD'
        $row['date'] = date('Y-M-d', strtotime($row['created_at']));
        $row['author'] = 'CCS Admin'; // Defaulting to admin since only admins create these
        array_push($announcements, $row);
    }

    http_response_code(200);
    echo json_encode($announcements);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Database error: ' . $e->getMessage()]);
}
