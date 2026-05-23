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
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        // Additional strict check to ensure TLD is alphabetic (e.g., rejects .com222)
        return preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email) === 1;
    }

    /**
     * Sanitize an email address.
     */
    public static function sanitizeEmail($email) {
        return filter_var($email, FILTER_SANITIZE_EMAIL);
    }

    /**
     * Validate Student ID (exactly 8 digits).
     */
    public static function isValidStudentId($student_id) {
        return preg_match('/^\d{8}$/', $student_id);
    }

    /**
     * Validate Names (letters, spaces, hyphens, apostrophes, dots, and ñ).
     */
    public static function isValidName($name) {
        if (empty($name)) return false;
        // Supports letters, spaces, hyphens, apostrophes, dots and Filipino characters
        return preg_match("/^[a-zA-Z\s\-'.ñÑ]+$/u", $name);
    }

    /**
     * Validate Address (Optional, any string allowed).
     */
    public static function isValidAddress($address) {
        if (empty($address)) return true; // Optional
        return is_string($address);
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