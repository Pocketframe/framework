<?php

/**
 * Helper Functions
 * 
 * This file contains commonly used helper functions for the framework.
 * Each function is wrapped in function_exists() check to prevent conflicts.
 */

use Pocketframe\Sessions\Session;
use Exception;
use Pocketframe\Http\Response\Response;

/**
 * Get the absolute path from the base directory
 * 
 * @param string $path The relative path to append
 * @return string The absolute path from base directory
 */
if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return BASE_PATH . $path;
    }
}

/**
 * Check if the current URL matches a given path
 * 
 * @param string $value The URL path to check against
 * @return bool True if current URL matches, false otherwise
 */
if (!function_exists('urlIs')) {
    function urlIs(string $value): bool
    {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        return $uri === $value;
    }
}

/**
 * Abort the request with an error page
 * 
 * @param int $code HTTP status code (defaults to 404)
 * @return void Dies after displaying error page
 */
if (!function_exists('abort')) {
    function abort(int $code = Response::NOT_FOUND)
    {
        http_response_code($code);
        require "views/errors/{$code}.php";
        die();
    }
}

/**
 * Authorize a condition or abort with error
 * 
 * @param bool $condition The condition to check
 * @param int $status HTTP status code on failure (defaults to 403)
 * @return void Aborts if condition is false
 */
if (!function_exists('authorize')) {
    function authorize(bool $condition, int $status = Response::FORBIDDEN)
    {
        if (!$condition) {
            abort($status);
        }
    }
}

/**
 * Redirect to another URL path
 * 
 * @param string $path The URL path to redirect to
 * @return void Dies after sending redirect header
 */
if (!function_exists('redirect')) {
    function redirect(string $path)
    {
        $path = '/' . ltrim($path, '/');
        $path = str_replace(["\r", "\n"], '', $path);
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $url = $protocol . '://' . $host . $path;
        header('Location: ' . $url, true, 302);
        exit();
    }
}

/**
 * Get old input value from session
 * 
 * @param string $key The input field key
 * @param mixed $default Default value if key not found
 * @return mixed The old input value or default     
 */
if (!function_exists('old')) {
    function old(string $key, $default = null)
    {
        return Session::get('old')[$key] ?? $default;
    }
}

/**
 * Get environment variable
 * 
 * @param string $key The environment variable key
 * @param mixed $default Default value if key not found
 * @return mixed The environment variable value or default
 */
if (!function_exists('env')) {
    function env(string $key, $default = null)
    {
        return getenv($key) ?: $default;
    }
}

/**
 * Convert a number to words
 * 
 * @param int $value The number to convert
 * @return string The number in words
 */
if (!function_exists('numberToWords')) {
    function numberToWords(int $value): string
    {
        $words = new NumberFormatter("en", NumberFormatter::SPELLOUT);
        return ucwords($words->format($value));
    }
}

/**
 * Generate a CSRF token
 * 
 * @return string The generated CSRF token
 */
if (!function_exists('csrfToken')) {
    function csrfToken(): string
    {
        if (!isset($_SESSION)) {
            session_start();
        }

        if (empty($_SESSION['csrf_token'])) {
            try {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            } catch (Exception $e) {
                // Fallback to less secure but still random token if random_bytes fails
                $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
            }
        }

        return $_SESSION['csrf_token'];
    }
}

/**
 * Validate a CSRF token
 * 
 * @param string $token The CSRF token to validate
 * @return bool True if token is valid, false otherwise
 */
if (!function_exists('validateCsrfToken')) {
    function validateCsrfToken(string $token): bool
    {
        if (!isset($_SESSION)) {
            session_start();
        }

        if (empty($token)) {
            return false;
        }

        return isset($_SESSION['csrf_token']) &&
            is_string($_SESSION['csrf_token']) &&
            is_string($token) &&
            hash_equals($_SESSION['csrf_token'], $token);
    }
}

/**
 * Get the full path to an asset
 * 
 * @param string $path The relative path to the asset
 * @return string The full path to the asset
 */
if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return BASE_PATH . 'public/' . ltrim($path, '/');
    }
}
