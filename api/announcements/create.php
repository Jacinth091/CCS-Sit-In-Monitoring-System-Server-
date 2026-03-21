<?php
require_once '../../includes/cors.php'; 
require_once '../../includes/initialize.php';

// Read JSON body
$data = json_decode(file_get_contents("php://input"));

if(!empty($data->title) && !empty($data->body)) {
    try {
        $query = "INSERT INTO announcements (title, content) VALUES (:title, :content)";
        $stmt = $db->prepare($query);

        $title = htmlspecialchars(strip_tags($data->title));
        $content = htmlspecialchars(strip_tags($data->body));

        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':content', $content);

        if($stmt->execute()) {
            http_response_code(201);
            echo json_encode(['message' => 'Announcement created.']);
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'Failed to create announcement.']);
        }
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['message' => 'Missing title or body.']);
}
