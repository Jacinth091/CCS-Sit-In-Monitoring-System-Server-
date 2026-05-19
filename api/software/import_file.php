<?php

require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/initialize.php';

requireAdmin();

// Validate required fields
$lab_id = $_POST['lab_id'] ?? null;
if (empty($lab_id)) {
    sendError(400, "Lab ID is required.");
}

// Validate file upload
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server limit.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form limit.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server missing temp folder.',
        UPLOAD_ERR_CANT_WRITE => 'Server cannot write to disk.',
    ];
    $code = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
    $msg = $uploadErrors[$code] ?? 'Unknown upload error.';
    sendError(400, $msg);
}

$file = $_FILES['file'];

// Validate file size (2MB max)
$maxSize = 2 * 1024 * 1024; // 2MB
if ($file['size'] > $maxSize) {
    sendError(400, "File too large. Maximum size is 2MB.");
}

// Validate file extension
$filename = $file['name'];
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
if (!in_array($ext, ['json', 'csv'])) {
    sendError(400, "Invalid file type. Only .json and .csv files are accepted.");
}

// Read and parse file content
$content = file_get_contents($file['tmp_name']);
if ($content === false) {
    sendError(500, "Failed to read uploaded file.");
}

$items = [];

try {
    if ($ext === 'json') {
        $parsed = json_decode($content, true);
        if (!is_array($parsed)) {
            sendError(400, "Invalid JSON: expected an array of objects.");
        }
        // Validate shape: each item must have at least 'name'
        foreach ($parsed as $idx => $entry) {
            if (!is_array($entry) || empty($entry['name'])) {
                sendError(400, "Invalid JSON at index $idx: each entry must have a 'name' field.");
            }
            $items[] = [
                'name'        => Validator::sanitizeString($entry['name']),
                'version'     => isset($entry['version']) ? Validator::sanitizeString($entry['version']) : null,
                'description' => isset($entry['description']) ? Validator::sanitizeString($entry['description']) : null,
            ];
        }
    } elseif ($ext === 'csv') {
        $lines = array_filter(array_map('trim', explode("\n", $content)));
        if (count($lines) < 2) {
            sendError(400, "CSV must have a header row and at least one data row.");
        }

        // Parse header
        $header = str_getcsv(array_shift($lines));
        $header = array_map(function($h) { return strtolower(trim($h)); }, $header);

        $nameIdx = array_search('name', $header);
        if ($nameIdx === false) {
            // Try 'software_name'
            $nameIdx = array_search('software_name', $header);
        }
        if ($nameIdx === false) {
            sendError(400, "CSV must have a 'name' or 'software_name' column.");
        }

        $versionIdx = array_search('version', $header);
        $descIdx = array_search('description', $header);

        foreach ($lines as $idx => $line) {
            $cols = str_getcsv($line);
            $name = trim($cols[$nameIdx] ?? '');
            if (empty($name)) continue; // Skip blank rows silently

            $items[] = [
                'name'        => Validator::sanitizeString($name),
                'version'     => ($versionIdx !== false && isset($cols[$versionIdx])) ? Validator::sanitizeString(trim($cols[$versionIdx])) : null,
                'description' => ($descIdx !== false && isset($cols[$descIdx])) ? Validator::sanitizeString(trim($cols[$descIdx])) : null,
            ];
        }

        if (empty($items)) {
            sendError(400, "No valid data rows found in CSV.");
        }
    }

    // Perform import
    $swModel = new Software($db);
    $result = $swModel->bulkInsertWithReport($lab_id, $items);

    sendSuccess(200, "File import completed.", $result);

} catch (Exception $e) {
    sendError(500, "Import failed.", $e);
}
