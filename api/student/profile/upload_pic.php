<?php
require_once __DIR__ . '/../../../includes/cors.php'; 
require_once __DIR__ . '/../../../includes/initialize.php';

$currentUser = requireAuth();

// Debugging: Log the request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // If post_max_size is exceeded, both $_POST and $_FILES will be empty
    if (empty($_FILES) && empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
        sendError(400, 'The uploaded file exceeds the server\'s post_max_size limit.');
    }
}

// Support both 'student_id' (string) and 'id' (UUID) in the request
// If not provided, use the authenticated user's student_id
$id_input = isset($_POST['student_id']) ? $_POST['student_id'] : (isset($_POST['id']) ? $_POST['id'] : $currentUser->student_id);

// Fetch student to get UUID and current profile pic path
$studentModel = new Student($db);
$studentData = null;

// Determine if input is a UUID or a student_id
if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id_input)) {
    $studentData = $studentModel->read_by_id($id_input);
} else {
    $studentData = $studentModel->read_by_student_id($id_input);
}

if (!$studentData) {
    sendError(404, 'Student not found.');
}

// Students can only upload their own photo; admins can upload for anyone
if ($currentUser->role === 'student' && $studentData['student_id'] !== $currentUser->student_id) {
    sendError(403, 'Access denied. You can only update your own profile picture.');
}

if(isset($_FILES['profile_pic'])) {
    $file = $_FILES['profile_pic'];
    
    // Use the robust ImageUploadHelper to handle directory creation, validation, and moving
    // It also handles deleting the old file if it exists.
    $upload = ImageUploadHelper::upload($file, 'profile', $studentData['profile_pic']);

    if ($upload['success']) {
        $db_path = $upload['path'];
        
        try {
            // Update database with the new path
            $query = "UPDATE students SET profile_pic = :pic, updated_at = NOW() WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':pic', $db_path);
            $stmt->bindParam(':id', $studentData['id']);
            
            if($stmt->execute()) {
                sendSuccess(200, 'Profile picture updated successfully.', ['profile_pic' => $db_path]);
            } else {
                // Note: The file is already moved. If DB fails, it remains on disk but untracked.
                sendError(500, 'Failed to update database record.');
            }
        } catch (Exception $e) {
            sendError(500, 'Database error: ' . $e->getMessage());
        }
    } else {
        // Return the specific error message from the helper (e.g., size, type, permissions)
        sendError(400, $upload['message']);
    }
} else {
    // Check if the request was actually a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError(405, 'Method Not Allowed. Use POST for uploads.');
    }
    sendError(400, 'No image file was received in the "profile_pic" field.');
}
