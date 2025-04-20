<?php

namespace Pocketframe\Sessions;

class Session
{
  protected array $data;
  protected const POCKET_KEY = '_pocket';

  public function __construct(array $data = [])
  {
    $this->data = $data;
  }

  public static function start(): void
  {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }

    if (!isset($_SESSION[self::POCKET_KEY])) {
      $_SESSION[self::POCKET_KEY] = [
        'flash' => [],
        '_old'   => [],
        'token' => null,
      ];
    }
  }

  public function all(): array
  {
    return $this->data;
  }

  public static function has(string $key): bool
  {
    self::start();
    return isset($_SESSION[$key]);
  }


  public static function put(string $key, mixed $value): void
  {
    self::start();
    $_SESSION[$key] = $value;
  }

  public static function get(string $key, mixed $default = null): mixed
  {
    self::start();

    if (str_contains($key, '.')) {
      [$main, $sub] = explode('.', $key, 2);
      return $_SESSION[$main][$sub] ?? $default;
    }

    return $_SESSION[$key] ?? $default;
  }

  // ---------- Flash Messages ----------

  public static function flash(string $key, mixed $value): void
  {
    self::start();
    $_SESSION[self::POCKET_KEY]['flash'][$key] = $value;
  }

  public static function hasFlash(string $key): bool
  {
    self::start();
    return isset($_SESSION[self::POCKET_KEY]['flash'][$key]);
  }

  public static function getFlash(string $key, mixed $default = null): mixed
  {
    self::start();
    $value = $_SESSION[self::POCKET_KEY]['flash'][$key] ?? $default;
    unset($_SESSION[self::POCKET_KEY]['flash'][$key]);
    return $value;
  }

  public static function flashAll(array $data): void
  {
    self::start();
    foreach ($data as $key => $value) {
      self::flash($key, $value);
    }
  }

  public static function flashOld(array $data): void
  {
    self::withOld($data);
  }

  // ---------- Old Input ----------
  public static function withOld(array $data): void
  {
    self::start();
    $_SESSION[self::POCKET_KEY]['_old'] = $data;
  }

  public static function old(string $key, mixed $default = null): mixed
  {
    self::start();
    $value = $_SESSION[self::POCKET_KEY]['_old'][$key] ?? $default;
    unset($_SESSION[self::POCKET_KEY]['_old'][$key]);
    return $value;
  }


  /**
   * Remove specific keys from the session.
   *
   * @param string|array $keys The key(s) to remove.
   */
  public static function remove(string|array $keys): void
  {
    self::start();
    foreach ((array) $keys as $key) {
      unset($_SESSION[$key]);
    }
  }


  public static function flush(): void
  {
    self::start();
    $_SESSION = [
      self::POCKET_KEY => [
        'flash' => [],
        '_old'   => [],
        'token' => null,
      ]
    ];
  }


  public static function expire()
  {
    self::start();
    unset($_SESSION['_flash']);
  }

  public static function sweep(): void
  {
    self::start();
    unset($_SESSION['_flash'], $_SESSION['_old']);
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
