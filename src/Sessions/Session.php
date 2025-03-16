<?php

namespace Pocketframe\Sessions;

class Session
{
  protected array $data;

  public function __construct(array $data = [])
  {
    $this->data = $data;
  }

  public static function start(): void
  {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }
  }

  public function all(): array
  {
    return $this->data;
  }

  public function has(string $key): bool
  {
    self::start();
    return isset($_SESSION[$key]);
  }

  public static function put($key, $value)
  {
    self::start();
    $_SESSION[$key] = $value;
  }

  public static function get($key, $default = null)
  {
    self::start();
    return $_SESSION[$key] ?? $default;
  }

  public static function flash($key, $value): void
  {
    self::start();
    $_SESSION['_flash'][$key] = $value;
  }

  public static function hasFlash($key)
  {
    self::start();
    return isset($_SESSION['_flash'][$key]);
  }

  public static function getFlash($key, $default = null)
  {
    self::start();
    $value = $_SESSION['_flash'][$key] ?? $default;
    unset($_SESSION['_flash'][$key]);
    return $value;
  }

  public static function old($key, $default = null)
  {
    self::start();
    $value = $_SESSION['_old'][$key] ?? $default;
    unset($_SESSION['_old'][$key]);
    return $value;
  }

  /**
   * Remove specific keys from the session.
   *
   * @param string|array $keys The key(s) to remove.
   */
  public static function remove($keys): void
  {
    self::start();
    if (is_string($keys)) {
      $keys = [$keys];
    }
    foreach ($keys as $key) {
      unset($_SESSION[$key]);
    }
  }

  public static function flush(): void
  {
    self::start();
    $_SESSION = [];
  }


  public static function expire()
  {
    self::start();
    unset($_SESSION['_flash']);
  }

  public static function destroy()
  {
    self::start();
    static::flush();

    session_destroy();

    $params = session_get_cookie_params();
    setcookie('PHPSESSID', '', time() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
  }
}
