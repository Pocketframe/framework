<?php

/**
 * Helper Functions
 *
 * This file contains commonly used helper functions for the framework.
 * Each function is wrapped in function_exists() check to prevent conflicts.
 */

use Pocketframe\Sessions\Session;
use Pocketframe\Http\Response\Response;
use Pocketframe\Routing\Router;

/**
 * Get the absolute path from the base directory
 *
 * This function returns the absolute path from the base directory by appending the given path to the base path.
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
 * This function checks if the current URL matches a given path.
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

if (!function_exists('error')) {
    /**
     * Retrieve the error messages for a given field.
     *
     * @return string[]
     */
    function error(string $field): array
    {
        return $_SESSION['errors'][$field] ?? [];
    }
}

/**
 * Abort the request with an error page
 *
 * This function aborts the request with an error page.
 *
 * @param int $code HTTP status code (defaults to Response::NOT_FOUND)
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
 * This function checks a condition and aborts with an error if the condition is false.
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
 * This function redirects to a given URL path.
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

if (!function_exists('display_errors')) {
    /**
     * Display validation error messages for a given field.
     */
    function display_errors(string $field): void
    {
        if (!empty($_SESSION['errors'][$field])) {
            foreach ($_SESSION['errors'][$field] as $err) {
                echo '<div class="error">' . htmlspecialchars($err) . '</div>';
            }
        }
    }
}

/**
 * Get old input value from session
 *
 * This function retrieves an old input value from the session.
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
 * This function retrieves an environment variable from the server environment.
 *
 * @param string $key The environment variable key
 * @param mixed $default Default value if key not found
 * @return mixed The environment variable value or default
 */
if (!function_exists('env')) {
    function env(string $key, $default = null)
    {
        $value = getenv($key);
        return $value !== false ? $value : $default;
    }
}

/**
 * Convert a number to words
 *
 * This function converts a number to words using the NumberFormatter class.
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
 * This function generates a CSRF token and stores it in the session.
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
 * This function validates a CSRF token by comparing it to the session token.
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
 * This function returns the full path to an asset by appending the asset path to the base path.
 *
 * @param string $path The relative path to the asset
 * @return string The full path to the asset
 */
if (!function_exists('asset')) {
    function asset(string $path): string
    {
        $scheme = $_SERVER['REQUEST_SCHEME'] ?? (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http'
        );

        return $scheme . '://' . $_SERVER['HTTP_HOST'] . '/' . ltrim($path, '/');
    }
}

/**
 * Sanitize a string
 *
 * This function sanitizes a string by converting special characters to HTML entities.
 *
 * @param string $string The string to sanitize
 * @return string The sanitized string
 */
if (!function_exists('sanitize')) {
    function sanitize(?string $string): string
    {
        return htmlspecialchars(trim($string), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Generate a method field
     *
     * This function generates a method field for HTML forms.
     *
     * @param string $method The HTTP method to generate the field for
     * @return string The generated method field
     */
    if (!function_exists('method')) {
        function method(string $method): string
        {
            return '<input type="hidden" name="_method" value="' . $method . '">';
        }
    }

    /**
     * Generate a CSRF field
     *
     * This function generates a CSRF field for HTML forms.
     *
     * @return string The generated CSRF field
     */
    if (!function_exists('csrf_token')) {
        function csrf_token(): string
        {
            if (!isset($_SESSION)) {
                session_start();
            }

            // Ensure the CSRF token is generated before accessing it
            if (empty($_SESSION['csrf_token'])) {
                csrfToken();
            }

            return '<input type="hidden" name="_token" value="' . $_SESSION['csrf_token'] . '">';
        }
    }
}

/**
 * Get the full path to a configuration file
 *
 * This function returns the full path to a configuration file by appending the configuration path to the base path.
 *
 * @param string $path The relative path to the configuration file
 * @return string The full path to the configuration file
 */
if (!function_exists('config_path')) {
    function config_path(string $path = ''): string
    {
        return base_path('config/' . $path . '.php');
    }
}

/**
 * Get a configuration value
 *
 * This function retrieves a configuration value from the configuration file.
 *
 * @param string $key The configuration key
 * @param mixed $default The default value if the key is not found
 * @return mixed The configuration value or default
 */
if (!function_exists('config')) {
    function config($key, $default = null)
    {
        static $config;

        if (!$config) {
            // $config = require BASE_PATH . '/config/app.php';
            $config = require config_path('app');
        }

        // Support dot notation like "app.debug"
        $keys = explode('.', $key);
        $value = $config;

        foreach ($keys as $key) {
            if (!is_array($value) || !isset($value[$key])) {
                return $default;
            }
            $value = $value[$key];
        }

        return $value;
    }
}


/**
 * Get the full path to a routes file
 *
 * This function returns the full path to a routes file by appending the routes path to the base path.
 *
 * @param string $path The relative path to the routes file
 * @return string The full path to the routes file
 */
if (!function_exists('routes_path')) {
    function routes_path(string $path = ''): string
    {
        return base_path('routes/' . $path . '.php');
    }
}

/**
 * Load environment variables from a file
 *
 * This function loads environment variables from a file and puts them into the $_ENV array.
 *
 * @param string $path The path to the environment file
 */
if (!function_exists('load_env')) {
    function load_env($path)
    {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            list($name, $value) = explode('=', $line, 2);
            putenv(trim($name) . '=' . trim($value));
        }
    }
}

/**
 * Configure error reporting settings
 *
 * This function sets up error reporting configuration by enabling all error types,
 * displaying errors on screen, showing startup errors, and specifying a log file
 * for error messages.
 *
 * @return void
 */
if (!function_exists('error_reporting')) {
    function error_report()
    {
        error_reporting(E_ALL);
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');
        ini_set('error_log', base_path('logs/pocketframe.log'));
    }
}


/**
 * Generate a URL for a named route
 *
 * This function generates a URL for a route with the given name and parameters.
 * It uses the Router singleton instance to look up the route pattern and
 * substitutes any route parameters to build the final URL.
 *
 * @param string $name The name of the route to generate a URL for
 * @param array $params Optional parameters to substitute in the route pattern
 * @return string The generated URL for the named route
 */
if (!function_exists('route')) {
    function route(string $name, array $params = []): string
    {
        global $router;

        if (!isset($router)) {
            throw new InvalidArgumentException("Router instance not available.");
        }

        return $router->route($name, $params);
    }
}
