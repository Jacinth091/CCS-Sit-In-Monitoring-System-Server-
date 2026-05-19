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

// If ID is not in the POST data, use the authenticated user's ID
$student_id = isset($_POST['id']) ? $_POST['id'] : $currentUser->id;

// Students can only upload their own photo; admins can upload for anyone
if ($currentUser->role === 'student' && $student_id !== $currentUser->id) {
    sendError(403, 'Access denied. You can only update your own profile picture.');
}

// Pathing: Ensure we use the correct uploads folder. 
// Based on directory listing, it's in the root /uploads/profiles
$target_dir = SITE_ROOT . "/uploads/profiles/";

if (!file_exists($target_dir)) {
    if (!mkdir($target_dir, 0777, true)) {
        sendError(500, 'Failed to create upload directory at ' . $target_dir);
    }
}

if (!is_writable($target_dir)) {
    sendError(500, 'Upload directory is not writable: ' . $target_dir);
}

if(isset($_FILES['profile_pic'])) {
    $file = $_FILES['profile_pic'];
    
    // Check for PHP upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
            UPLOAD_ERR_PARTIAL    => 'The uploaded file was only partially uploaded',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload',
        ];
        $msg = isset($error_messages[$file['error']]) ? $error_messages[$file['error']] : 'Unknown upload error (Code: ' . $file['error'] . ')';
        sendError(400, $msg);
    }
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    // Use mime_content_type if finfo is not available
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    } elseif (function_exists('mime_content_type')) {
        $mime = mime_content_type($file['tmp_name']);
    } else {
        $mime = $file['type']; // Fallback to browser-provided type
    }

    if(!in_array($mime, $allowed_types)) {
        sendError(400, 'Invalid file type (' . $mime . '). Only JPG, PNG, GIF, and WEBP are allowed.');
    }
    
    // Generate unique name
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (empty($ext)) {
        $mime_map = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp'
        ];
        $ext = isset($mime_map[$mime]) ? $mime_map[$mime] : 'bin';
    }
    
    $filename = uniqid('profile_') . '.' . $ext;
    $target_file = $target_dir . $filename;
    
    if(move_uploaded_file($file['tmp_name'], $target_file)) {
        // This path is stored in DB. 
        $db_path = 'uploads/profiles/' . $filename;
        
        try {
            // Update database
            $query = "UPDATE students SET profile_pic = :pic WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':pic', $db_path);
            $stmt->bindParam(':id', $student_id);
            
            if($stmt->execute()) {
                sendSuccess(200, 'Profile picture updated successfully.', ['profile_pic' => $db_path]);
            } else {
                sendError(500, 'Failed to update database record.');
            }
        } catch (Exception $e) {
            sendError(500, 'Database error: ' . $e->getMessage());
        }
    } else {
        sendError(500, 'Failed to move uploaded file to destination.');
    }
} else {
    // Check if the request was actually a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError(405, 'Method Not Allowed. Use POST for uploads.');
    }
    sendError(400, 'No image file was received in the "profile_pic" field.');
}
