<?php

class Logger {
    /**
     * Log an informational message (Cyan)
     */
    public static function info($message, $data = null) {
        self::log('INFO', $message, $data, "\033[36m");
    }

    /**
     * Log a success message (Green)
     */
    public static function success($message, $data = null) {
        self::log('SUCCESS', $message, $data, "\033[32m");
    }

    /**
     * Log a warning message (Yellow)
     */
    public static function warning($message, $data = null) {
        self::log('WARNING', $message, $data, "\033[33m");
    }

    /**
     * Log an error message (Red)
     */
    public static function error($message, $data = null) {
        self::log('ERROR', $message, $data, "\033[31m");
    }

    /**
     * Log an incoming HTTP request (Magenta)
     */
    public static function request($method, $uri) {
        $color = "\033[35m"; // Magenta
        $reset = "\033[0m";
        $date = date('Y-m-d H:i:s');
        
        $logStr = "[$date] {$color}[REQUEST]{$reset} $method $uri";
        file_put_contents('php://stderr', $logStr . PHP_EOL);
    }

    /**
     * Internal logging mechanism writing directly to standard error
     */
    private static function log($level, $message, $data, $color) {
        $reset = "\033[0m";
        $date = date('Y-m-d H:i:s');
        
        $logStr = "[$date] {$color}[{$level}]{$reset} $message";
        
        if ($data !== null) {
            $logStr .= " | Data: " . json_encode($data);
        }
        
        // Writing to php://stderr ensures it shows up immediately in the terminal
        // when running via PHP's built-in web server.
        file_put_contents('php://stderr', $logStr . PHP_EOL);
    }
}
