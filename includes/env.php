<?php

function load_env($path) {
    if (!file_exists($path)) {
        throw new Exception('.env file not found at: ' . $path);
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        // Skip comments
        if (str_starts_with(trim($line), '#')) continue;

        [$key, $value] = explode('=', $line, 2);

        $key   = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'"); // strip quotes too

        $_ENV[$key] = $value;
        putenv("$key=$value");
    }
}