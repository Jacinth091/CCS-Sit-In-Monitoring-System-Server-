<?php

class Validator {
    /**
     * Sanitize a string by stripping tags and special characters.
     */
    public static function sanitizeString($string) {
        if ($string === null) return '';
        return htmlspecialchars(strip_tags(trim($string)));
    }

    /**
     * Validate an email address.
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Sanitize an email address.
     */
    public static function sanitizeEmail($email) {
        return filter_var($email, FILTER_SANITIZE_EMAIL);
    }

    /**
     * Validate a UUID.
     */
    public static function validateUUID($uuid) {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid);
    }

    /**
     * Validate if a value is numeric.
     */
    public static function isNumeric($value) {
        return is_numeric($value);
    }

    /**
     * Sanitize an integer.
     */
    public static function sanitizeInt($value) {
        return filter_var($value, FILTER_SANITIZE_NUMBER_INT);
    }
}