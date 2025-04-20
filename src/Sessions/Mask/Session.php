<?php

namespace Pocketframe\Sessions\Mask;

use Pocketframe\Sessions\SessionManager;

class Session
{
  protected const KEY = '_pocket';

  /**
   * Start the session if it is not already started.
   * If the session is already started, this method does nothing.
   * This method is used to ensure that the session is started before any
   * session data is accessed or modified.
   *
   * @return void
   */
  public static function start(): void
  {
    SessionManager::start();
  }

  /**
   * Check if the session is started.
   * This method checks if the session is already started by checking
   * if the session ID is set. If the session ID is not set, it means
   * that the session has not been started yet.
   *
   * @return bool
   */
  public static function isStarted(): bool
  {
    return SessionManager::isStarted();
  }

  /**
   * Get the session ID.
   * This method returns the current session ID. If the session is not
   * started, it will return an empty string.
   *
   * @return string
   */
  public static function id(): string
  {
    self::start();
    return session_id();
  }
  /**
   * Get all session data.
   * This method returns all session data as an associative array.
   * It starts the session if it is not already started.
   * This method is useful for retrieving all session data at once.
   */
  public static function all(): array
  {
    self::start();
    return $_SESSION;
  }

  /**
   * Check if a session key exists.
   * This method checks if a specific session key exists in the session data.
   * It starts the session if it is not already started.
   * This method is useful for checking if a specific session variable is set.
   *
   * @param string $key The session key to check.
   * @return bool True if the key exists, false otherwise.
   */
  public static function exists(string $key): bool
  {
    return self::has($key);
  }

  /**
   * Check if has a session key.
   * This method checks if a specific session key exists in the session data.
   * It starts the session if it is not already started.
   * This method is useful for checking if a specific session variable is set.
   *
   * @param string $key The session key to check.
   * @return bool True if the key exists, false otherwise.
   */
  public static function has(string $key): bool
  {
    self::start();
    $segments = explode('.', $key);
    $data = $_SESSION;

    foreach ($segments as $segment) {
      if (!is_array($data) || !array_key_exists($segment, $data)) {
        return false;
      }
      $data = $data[$segment];
    }

    return true;
  }

  /**
   * Get a session value by key.
   * This method retrieves the value of a specific session key.
   * If the key does not exist, it returns the provided default value.
   * It starts the session if it is not already started.
   *
   * @param string $key The session key to retrieve.
   * @param mixed $default The default value to return if the key does not exist.
   * @return mixed The value of the session key or the default value.
   */
  public static function get(string $key, $default = null)
  {
    self::start();

    $segments = explode('.', $key);
    $data = $_SESSION;

    foreach ($segments as $segment) {
      if (is_array($data) && array_key_exists($segment, $data)) {
        $data = $data[$segment];
      } else {
        return $default;
      }
    }

    return $data;
  }

  /**
   * Store a value in the session.
   * This method stores a value in the session using the provided key.
   * If the session is not already started, it will start the session.
   * This method is useful for storing data in the session.
   *
   * @param string $key The session key to store the value under.
   * @param mixed $value The value to store in the session.
   * @return void
   */
  public static function put(string $key, $value): void
  {
    self::start();
    $segments = explode('.', $key);
    $data = &$_SESSION;

    foreach ($segments as $segment) {
      if (!isset($data[$segment]) || !is_array($data[$segment])) {
        $data[$segment] = [];
      }
      $data = &$data[$segment];
    }

    $data = $value;
  }

  /**
   * Store multiple values in the session.
   * This method stores multiple values in the session using the provided
   * associative array. The keys of the array are used as session keys.
   * If the session is not already started, it will start the session.
   * This method is useful for storing multiple data points in the session.
   *
   * @param array $data The associative array of key-value pairs to store in the session.
   * @return void
   * @example Session::putAll(['name' => 'John', 'age' => 30]);
   */
  public static function putAll(array $data): void
  {
    self::start();
    foreach ($data as $k => $v) {
      self::put($k, $v);
    }
  }

  /**
   * Remove a session key.
   * This method removes a specific session key from the session data.
   * If the session is not already started, it will start the session.
   * This method is useful for clearing specific session variables.
   *
   * @param string|array $keys The session key(s) to remove.
   * @return void
   */
  public static function remove(string|array $keys): void
  {
    self::start();
    foreach ((array)$keys as $k) {
      unset($_SESSION[$k]);
    }
  }

  /**
   * Clear all session data.
   * This method clears all session data by resetting the $_SESSION array.
   * If the session is not already started, it will start the session.
   * This method is useful for clearing all session variables at once.
   *
   * @return void
   */
  public static function clear(): void
  {
    self::start();
    $_SESSION = [];
  }

  /**
   * Flush the session data.
   * This method clears all session data and resets the session to its initial state.
   * It starts the session if it is not already started.
   * This method is useful for resetting the session completely.
   *
   * @return void
   */
  public static function flush(): void
  {
    self::start();
    $_SESSION = [self::KEY => ['flash' => [], '_old' => []]];
  }

  /**
   * Destroy the session.
   * This method destroys the current session and clears all session data.
   * It starts the session if it is not already started.
   * This method is useful for logging out users or clearing all session data.
   *
   * @return void
   */
  public static function destroy(): void
  {
    self::start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
      $p = session_get_cookie_params();
      setcookie(
        session_name(),
        '',
        time() - 42000,
        $p['path'],
        $p['domain'],
        $p['secure'],
        $p['httponly']
      );
    }
    session_destroy();
  }

  /**
   * Store a flash message in the session.
   * This method stores a flash message in the session using the provided key.
   * Flash messages are temporary and will be removed after being accessed.
   * If the session is not already started, it will start the session.
   * This method is useful for storing messages that should only be displayed once.
   *
   * @param string $key The session key to store the flash message under.
   * @param mixed $value The flash message to store in the session.
   * @return void
   * @example Session::flash('success', 'Your changes have been saved.');
   */
  public static function flash(string $key, $value): void
  {
    self::start();
    $_SESSION[self::KEY]['flash'][$key] = $value;
  }

  /**
   * Check if a flash message exists in the session.
   * This method checks if a specific flash message key exists in the session data.
   * Flash messages are temporary and will be removed after being accessed.
   * If the session is not already started, it will start the session.
   * This method is useful for checking if a specific flash message is set.
   *
   * @param string $key The flash message key to check.
   * @return bool True if the flash message key exists, false otherwise.
   */
  public static function hasFlash(string $key): bool
  {
    self::start();
    return isset($_SESSION[self::KEY]['flash'][$key]);
  }

  /**
   * Get a flash message from the session.
   * This method retrieves the value of a specific flash message key.
   * Flash messages are temporary and will be removed after being accessed.
   * If the session is not already started, it will start the session.
   * This method is useful for retrieving flash messages that should only be displayed once.
   *
   * @param string $key The flash message key to retrieve.
   * @param mixed $default The default value to return if the key does not exist.
   * @return mixed The value of the flash message key or the default value.
   */
  public static function getFlash(string $key, $default = null)
  {
    self::start();
    $val = $_SESSION[self::KEY]['flash'][$key] ?? $default;
    unset($_SESSION[self::KEY]['flash'][$key]);
    return $val;
  }

  /**
   * Get all flash messages from the session.
   * This method retrieves all flash messages stored in the session.
   * Flash messages are temporary and will be removed after being accessed.
   * If the session is not already started, it will start the session.
   * This method is useful for retrieving all flash messages at once.
   *
   * @return array The array of flash messages.
   */
  public static function flashAll(array $data): void
  {
    self::start();
    foreach ($data as $k => $v) {
      self::flash($k, $v);
    }
  }

  /**
   * Store old input data in the session.
   * This method stores old input data in the session using the provided
   * associative array. The keys of the array are used as session keys.
   * If the session is not already started, it will start the session.
   * This method is useful for preserving old input data across requests.
   *
   * @param array $data The associative array of key-value pairs to store as old input data.
   * @return void
   */
  public static function flashOld(array $data): void
  {
    self::start();
    $_SESSION[self::KEY]['_old'] = $data;
  }

  /**
   * Get old input data from the session.
   * This method retrieves the value of a specific old input key.
   * Old input data is used to preserve input across requests.
   * If the session is not already started, it will start the session.
   * This method is useful for retrieving old input data that was stored in the session.
   *
   * @param string $key The old input key to retrieve.
   * @param mixed $default The default value to return if the key does not exist.
   * @return mixed The value of the old input key or the default value.
   */
  public static function old(string $key, $default = null)
  {
    self::start();

    $data = $_SESSION[self::KEY]['_old'] ?? [];

    $segments = explode('.', $key);
    foreach ($segments as $segment) {
      if (!is_array($data) || !array_key_exists($segment, $data)) {
        return $default;
      }
      $data = $data[$segment];
    }

    unset($_SESSION[self::KEY]['_old'][$segments[0]]);

    return $data;
  }

  /**
   * Store old input data in the session.
   * This method stores old input data in the session using the provided
   * associative array. The keys of the array are used as session keys.
   * If the session is not already started, it will start the session.
   * This method is useful for preserving old input data across requests.
   *
   * @param array $data The associative array of key-value pairs to store as old input data.
   * @return void
   */
  public static function withOld(array $data): void
  {
    self::start();
    $_SESSION[self::KEY]['_old'] = $data;
  }
  /**
   * Expire flash messages.
   * This method clears all flash messages stored in the session.
   * Flash messages are temporary and will be removed after being accessed.
   * If the session is not already started, it will start the session.
   * This method is useful for expiring flash messages that have already been displayed.

   * @return void
   * @example Session::expire();
   */
  public static function expire(): void
  {
    self::start();
    $_SESSION[self::KEY]['flash'] = [];
  }

  /**
   * Sweep flash and old data
   * This method clears all flash messages and old input data stored in the session.
   * Flash messages are temporary and will be removed after being accessed.
   * Old input data is used to preserve input across requests.
   * If the session is not already started, it will start the session.
   * This method is useful for expiring both flash messages and old input data.
   *
   * @return void
   * @example Session::sweep();
   */
  public static function sweep(): void
  {
    self::start();
    $_SESSION[self::KEY]['flash'] = [];
    $_SESSION[self::KEY]['_old'] = [];
  }

  // Regenerate session ID
  public static function regenerate(bool $deleteOld = true): void
  {
    self::start();
    session_regenerate_id($deleteOld);
  }
}
