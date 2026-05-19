<?php

    require_once __DIR__ . '/../vendor/autoload.php';
    defined('DS') ? null : define('DS', DIRECTORY_SEPARATOR); 

    // Use the directory of this file to find the project root reliably
    defined('SITE_ROOT') ? null : define('SITE_ROOT', dirname(__DIR__));

    defined('INC_PATH')   ? null : define('INC_PATH',   SITE_ROOT . DS . 'includes');
    defined('SRC_PATH')   ? null : define('SRC_PATH',   SITE_ROOT . DS . 'src');
    defined('MODEL_PATH') ? null : define('MODEL_PATH', SRC_PATH  . DS . 'models');
    defined('CONT_PATH')  ? null : define('CONT_PATH',  SRC_PATH  . DS . 'controllers');
    defined('DB_PATH')    ? null : define('DB_PATH',    SRC_PATH  . DS . 'database');
    defined('UTIL_PATH')  ? null : define('UTIL_PATH',  SRC_PATH  . DS . 'utils');

    require_once(INC_PATH . DS . 'config.php');
    require_once(INC_PATH . DS . 'validate_token.php');
    require_once(INC_PATH . DS . 'validator.php');
    require_once(INC_PATH . DS . 'logger.php');
    require_once(MODEL_PATH . DS . 'Student.php');
    require_once(MODEL_PATH . DS . 'SitIn.php');
    require_once(MODEL_PATH . DS . 'Dashboard.php');
    require_once(MODEL_PATH . DS . 'Announcement.php');
    require_once(MODEL_PATH . DS . 'Report.php');
    require_once(MODEL_PATH . DS . 'Reservation.php');
    require_once(MODEL_PATH . DS . 'Software.php');
    require_once(MODEL_PATH . DS . 'Laboratory.php');
    require_once(MODEL_PATH . DS . 'Testimonial.php');
    require_once(MODEL_PATH . DS . 'PC.php');
    require_once(MODEL_PATH . DS . 'AuditLog.php');
    require_once(INC_PATH . DS . 'notifications.php');

    // Log incoming requests if running via CLI server or XAMPP
    if (isset($_SERVER['REQUEST_METHOD']) && isset($_SERVER['REQUEST_URI'])) {
        Logger::request($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
    }

    /**
     * Standardized Validation Error Handler
     */
    function sendValidationError($errors) {
        http_response_code(422);
        echo json_encode([
            'status' => 'error',
            'message' => 'Validation failed',
            'errors' => $errors
        ]);
        exit();
    }

    function sendError($httpCode, $userMessage, $exception = null) {
        http_response_code($httpCode);
        
        $response = [
            'status' => 'error',
            'message' => $userMessage
        ];

        if ($exception !== null) {
            $response['debug'] = [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'sql_error' => $exception->getMessage(),
                'sql_state' => $exception->getCode() 
            ];
        }

        echo json_encode($response);
        exit(); // Crucial: This stops the script from double-printing JSON
    }

    /**
     * Standardized Success Handler for the API
     */
    function sendSuccess($httpCode, $message, $data = null, $meta = null) {
        // 1. Set the HTTP status code (e.g., 200 OK, 201 Created)
        http_response_code($httpCode);
        
        // 2. Build the base response
        $response = [
            'status' => 'success',
            'message' => $message
        ];

        // 3. If data was passed, attach it.
        if ($data !== null) {
            $response['data'] = $data;
        }

        // 4. If meta data was passed, attach it.
        if ($meta !== null) {
            $response['meta'] = $meta;
        }

        // 5. Output and stop
        echo json_encode($response);
        exit(); 
    }
?>