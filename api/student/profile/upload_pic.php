<?php
require_once __DIR__ . '/../../../includes/cors.php'; 
require_once __DIR__ . '/../../../includes/initialize.php';

$currentUser = requireAuth();

// If ID is not in the POST data, use the authenticated user's ID
$student_id = isset($_POST['id']) ? $_POST['id'] : $currentUser->id;

// Students can only upload their own photo; admins can upload for anyone
if ($currentUser->role === 'student' && $student_id !== $currentUser->id) {
    sendError(403, 'Access denied. You can only update your own profile picture.');
}

// Check if upload directory exists
$target_dir = "../../uploads/profiles/";
if (!file_exists($target_dir)) {
    mkdir($target_dir, 0777, true);
}

if(isset($_FILES['profile_pic'])) {
    $file = $_FILES['profile_pic'];
    
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if(!in_array($file['type'], $allowed_types)) {
        http_response_code(400);
        echo json_encode(['message' => 'Invalid file type. Only JPG, PNG, GIF, WEBP allowed.']);
        exit();
    }
    
    // Generate unique name
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('profile_') . '.' . $ext;
    $target_file = $target_dir . $filename;
    
    if(move_uploaded_file($file['tmp_name'], $target_file)) {
        $db_path = 'uploads/profiles/' . $filename;
        
        // Update database
        $query = "UPDATE students SET profile_pic = :pic WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':pic', $db_path);
        $stmt->bindParam(':id', $student_id);
        
        if($stmt->execute()) {
            http_response_code(200);
            echo json_encode(['message' => 'Profile picture uploaded.', 'profile_pic' => $db_path]);
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'Failed to update database.']);
        }
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Failed to save file.']);
    }
} else {
    http_response_code(400);
    echo json_encode(['message' => 'Missing image file.']);
}
