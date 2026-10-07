<?php
/**
 * Environment & Secure Configuration Manager
 * Loads key-value pairs from .env without exposing credentials to frontend or logs.
 */

class Env {
    private static $loaded = false;
    private static $variables = [];

    /**
     * Load .env file from project root if present
     */
    public static function load($filePath = null) {
        if (self::$loaded && $filePath === null) {
            return;
        }

        if ($filePath === null) {
            $filePath = __DIR__ . '/../.env';
        }

        if (file_exists($filePath) && is_readable($filePath)) {
            $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                // Skip comments and empty lines
                if (empty($line) || str_starts_with($line, '#') || str_starts_with($line, ';')) {
                    continue;
                }

                $pos = strpos($line, '=');
                if ($pos !== false) {
                    $key = trim(substr($line, 0, $pos));
                    $value = trim(substr($line, $pos + 1));

                    // Strip surrounding quotes if present
                    if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                        (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                        $value = substr($value, 1, -1);
                    }

                    // Remove spaces from Gmail app password if formatted like "abcd efgh ijkl mnop"
                    if (strtoupper($key) === 'MAIL_PASSWORD') {
                        $value = str_replace(' ', '', $value);
                    }

                    self::$variables[$key] = $value;
                    if (!isset($_ENV[$key])) {
                        $_ENV[$key] = $value;
                    }
                    if (!isset($_SERVER[$key])) {
                        $_SERVER[$key] = $value;
                    }
                    putenv("{$key}={$value}");
                }
            }
        }

        self::$loaded = true;
    }

    /**
     * Get an environment variable with fallback
     */
    public static function get($key, $default = null) {
        if (!self::$loaded) {
            self::load();
        }

        if (array_key_exists($key, self::$variables)) {
            return self::$variables[$key];
        }

        $val = getenv($key);
        if ($val !== false) {
            return $val;
        }

        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }

        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }

        return $default;
    }

    /**
     * Mask sensitive strings for safe debug view
     */
    public static function mask($string, $visibleChars = 2) {
        if (empty($string)) return '';
        $len = strlen($string);
        if ($len <= ($visibleChars * 2)) {
            return str_repeat('*', $len);
        }
        return substr($string, 0, $visibleChars) . str_repeat('*', max(4, $len - ($visibleChars * 2))) . substr($string, -$visibleChars);
    }
}

// Global helper function
if (!function_exists('env')) {
    function env($key, $default = null) {
        return Env::get($key, $default);
    }
}

// Auto-load on include
Env::load();
