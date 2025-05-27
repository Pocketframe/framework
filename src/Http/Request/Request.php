<?php

namespace Pocketframe\Http\Request;

use Pocketframe\Sessions\Mask\Session;

class Request
{
  protected array $sessionData = [];

  /**
   * Get HTTP Request Method
   *
   * Returns the HTTP method used for the current request. Supports method spoofing
   * through POST requests with a '_method' parameter, allowing simulation of PUT,
   * PATCH, and DELETE requests from HTML forms. If no method is specified, defaults
   * to 'GET'. The returned method is always uppercase.
   *
   * @return string The HTTP method (GET, POST, PUT, PATCH, DELETE, etc.)
   */
  public function method(): string
  {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'POST' && isset($_POST['_method'])) {
      return strtoupper($_POST['_method']);
    }

    return strtoupper($method);
  }

  /**
   * Check if the request method matches a specific method
   *
   * Returns true if the request method matches the specified method.
   *
   * @param string $method The method to check against
   * @return bool True if the request method matches, false otherwise
   */
  public function isMethod(string $method): bool
  {
    return strtoupper($method) === $this->method();
  }

  /**
   * Get the URI of the current request
   *
   * Returns the URI of the current request.
   *
   * @return string The URI of the current request
   */
  public function uri()
  {
    return parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
  }

  /**
   * Get all request data
   *
   * Returns an associative array containing all request data from GET and POST parameters.
   *
   * @return array<string, mixed> The request data
   */
  public function all(): array
  {
    return array_merge($_GET, $_POST, $_FILES);
  }

  /**
   * Get a specific request parameter
   *
   * Returns the value of a specific request parameter from GET or POST data.
   *
   * @param string $key The key of the parameter to retrieve
   * @param mixed $default The default value to return if the parameter is not found
   * @return mixed The value of the parameter or the default value
   */
  public function get(string $key, mixed $default = null): mixed
  {
    $value = $_GET[$key] ?? $default;
    return $this->sanitize($value);
  }

  /**
   * Get a specific POST parameter
   *
   * Returns the value of a specific POST parameter from POST data.
   *
   * @param string $key The key of the parameter to retrieve
   * @param mixed $default The default value to return if the parameter is not found
   * @return mixed The value of the parameter or the default value
   */
  public function post(string $key, mixed $default = null): mixed
  {
    $value = $_POST[$key] ?? $default;
    return $this->sanitize($value);
  }

  /**
   * Get a specific request parameter
   *
   * Returns the value of a specific request parameter from GET or POST data.
   *
   * @param string $key The key of the parameter to retrieve
   * @param mixed $default The default value to return if the parameter is not found
   * @return mixed The value of the parameter or the default value
   */
  public function input(string $key, mixed $default = null): mixed
  {
    $value = $_POST[$key] ?? $default;
    return $this->sanitize($value);
  }

  /**
   * Get JSON request data
   *
   * Retrieves and parses JSON data from the request body when the Content-Type is application/json.
   * If a key is provided, returns the value for that specific key from the JSON data.
   * If the request is not JSON or the key is not found, returns the default value.
   *
   * @param string|null $key The key to retrieve from the JSON data. If null, returns all JSON data
   * @param mixed $default The default value to return if the key is not found or request is not JSON
   * @return mixed The JSON data, specific key value, or default value
   */
  public function json(string $key = null, mixed $default = null): mixed
  {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
      $data = json_decode(file_get_contents('php://input'), true);
      if ($key) {
        return $this->sanitize($data[$key] ?? $default);
      }
      return $data;
    }
    return $default;
  }

  /**
   * Check if the request is a JSON request
   *
   * Returns true if the request header indicates that the client is requesting a JSON response.
   *
   * @return bool True if the request is a JSON request, false otherwise
   */
  public function isJson(): bool
  {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    return strpos($contentType, 'application/json') !== false;
  }

  /**
   * Get a specific file upload
   *
   * Returns the value of a specific file upload from the $_FILES array.
   *
   * @param string $key The key of the file upload to retrieve
   * @param mixed $default The default value to return if the file upload is not found
   * @return mixed The file upload data or the default value
   */
  public function file(string $key, mixed $default = null): mixed
  {
    if (isset($_FILES[$key])) {
      return new UploadedFile($_FILES[$key]);
    }
    return $default;
  }

  public function storeFileOrNull(string $key, string $directory, string $disk = 'public'): ?string
  {
    return $this->hasFile($key)
      ? $this->file($key)->store($directory, $disk)
      : null;
  }

  /**
   * Check if a specific file upload exists
   *
   * Returns true if a specific file upload exists in the $_FILES array.
   *
   * @param string $key The key of the file upload to check
   * @return bool True if the file upload exists, false otherwise
   */
  public function hasFile(string $key): bool
  {
    return isset($_FILES[$key]) && $_FILES[$key]['error'] !== UPLOAD_ERR_NO_FILE;
  }

  /**
   * Check if a specific request parameter exists
   *
   * Returns true if a specific request parameter exists in the $_REQUEST array.
   *
   * @param string $key The key of the request parameter to check
   * @return bool True if the request parameter exists, false otherwise
   */
  public function has(string $key): bool
  {
    return isset($_REQUEST[$key]);
  }

  /**
   * Check if a specific request parameter is filled
   *
   * Returns true if a specific request parameter is filled in the $_REQUEST array.
   *
   * @param string $key The key of the request parameter to check
   * @return bool True if the request parameter is filled, false otherwise
   */
  public function filled(string $key): bool
  {
    return !empty($_REQUEST[$key]);
  }


  /**
   * Sanitize input
   *
   * Sanitizes the input by converting special characters to HTML entities.
   *
   * @param mixed $input The input to sanitize
   * @return mixed The sanitized input
   */
  public function sanitize($input)
  {
    if (is_array($input)) {
      return array_map(function ($item) {
        return is_array($item) ? $item : htmlspecialchars(trim($item ?? ''), ENT_QUOTES, 'UTF-8');
      }, $input);
    }

    return htmlspecialchars(trim($input ?? ''), ENT_QUOTES, 'UTF-8');
  }

  /**
   * Check if the request expects a JSON response
   *
   * Returns true if the request header indicates that the client expects a JSON response.
   *
   * @return bool True if the request expects a JSON response, false otherwise
   */
  public function expectsJson(): bool
  {
    return $this->header('Accept') === 'application/json'
      || $this->header('Content-Type') === 'application/json';
  }

  /**
   * Get a specific request header
   *
   * Returns the value of a specific request header.
   *
   * @param string $key The key of the header to retrieve
   * @param string|null $default The default value to return if the header is not found
   * @return string|null The value of the header or the default value
   */
  public function header(string $key, ?string $default = null): ?string
  {
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
    return $_SERVER[$key] ?? $default;
  }

  /**
   * Get a specific cookie
   *
   * Returns the value of a specific cookie.
   *
   * @param string $key The key of the cookie to retrieve
   * @param mixed $default The default value to return if the cookie is not found
   * @return mixed The value of the cookie or the default value
   */
  public function cookie(string $key, mixed $default = null): mixed
  {
    return $this->sanitize($_COOKIE[$key] ?? $default);
  }

  /**
   * Check if the request is an AJAX request
   *
   * Returns true if the request header indicates that the client is an AJAX request.
   *
   * @return bool True if the request is an AJAX request, false otherwise
   */
  public function isAjax(): bool
  {
    return $this->header('X-Requested-With') === 'XMLHttpRequest';
  }

  /**
   * Get the full URL of the current request
   *
   * Returns the full URL of the current request.
   *
   * @return string The full URL of the current request
   */
  public function url(): string
  {
    return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
  }

  /**
   * Get the previous URL
   *
   * Returns the previous URL of the current request.
   *
   * @param string $default The default URL to return if the previous URL is not set
   * @return string The previous URL of the current request
   */
  public function previous(string $default = '/'): string
  {
    return $_SERVER['HTTP_REFERER'] ?? $default;
  }

  /**
   * Get the previous URL
   *
   * Returns the previous URL of the current request.
   *
   * @return string The previous URL of the current request
   */
  public function back(): string
  {
    return $this->previous();
  }

  /**
   * Get the user agent
   *
   * Returns the user agent of the current request.
   *
   * @return string|null The user agent of the current request or null if not set
   */
  public function userAgent(): ?string
  {
    return $_SERVER['HTTP_USER_AGENT'] ?? null;
  }

  /**
   * Get the IP address of the current request
   *
   * Returns the IP address of the current request.
   *
   * @return string|null The IP address of the current request or null if not set
   */
  public function ip(): ?string
  {
    return $_SERVER['HTTP_CLIENT_IP'] ??
      $_SERVER['HTTP_X_FORWARDED_FOR'] ??
      $_SERVER['REMOTE_ADDR'] ?? null;
  }

  /**
   * Check if the request is secure
   *
   * Returns true if the request is secure.
   *
   * @return bool True if the request is secure, false otherwise
   */
  public function isSecure(): bool
  {
    return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
  }

  /**
   * Set the session data
   *
   * Sets the session data for the request.
   *
   * @param array<string, mixed> $data The session data to set
   */
  public function setSession(array $data): void
  {
    $this->sessionData = $data;
  }

  /**
   * Get the session object
   *
   * Returns a new session object. If the session is not already started, it will be started.
   *
   * @return Session The session object
   */
  public function session(): Session
  {
    // Ensure the session manager is started
    Session::start();

    // If the session is not already started, start it
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }

    // Return the session object
    return new Session($this->sessionData);
  }
  /**
   * Get the request path
   *
   * Returns the request path from the server variables.
   *
   * @return string The request path
   */
  public function path(): string
  {
    return $_SERVER['REQUEST_URI'] ?? '/';
  }
  /**
   * Get the request path segments
   *
   * Returns an array of path segments from the request URI.
   *
   * @return array<string> The request path segments
   */
  public function segments(): array
  {
    $path = $this->path();
    $segments = explode('/', trim($path, '/'));
    return array_filter($segments);
  }

  /**
   * Check if the request wants a JSON response
   *
   * Returns true if the request header indicates that the client wants a JSON response.
   *
   * @return bool True if the request wants a JSON response, false otherwise
   */
  public function wantsJson(): bool
  {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return strpos($accept, 'application/json') !== false;
  }
}
