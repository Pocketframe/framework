<?php

namespace Pocketframe\Sessions;

use Pocketframe\PocketORM\Database\Connection;
use Pocketframe\Sessions\Storage\CookieSessionHandler;
use Pocketframe\Sessions\Storage\DatabaseSessionHandler;

class SessionManager
{
  protected static ?self $instance = null;

  /** @var array<string,mixed> */
  protected array $config;

  protected function __construct()
  {
    $this->config = config('session');

    // Only configure PHP session if not already started
    if (session_status() === PHP_SESSION_NONE) {
      $this->configureCookieParams();
      $this->configureSaveHandler();
      session_start();
    }

    // Boot our namespace for flash & old
    if (! isset($_SESSION['_pocket'])) {
      $_SESSION['_pocket'] = ['flash' => [], '_old' => []];
    }
  }

  /**
   * Public facade to ensure the session system is initialized.
   */
  public static function start(): void
  {
    self::instance();
  }

  /**
   * Check if the session is started.
   *
   * @return bool
   */
  public static function isStarted(): bool
  {
    return session_status() === PHP_SESSION_ACTIVE;
  }

  public static function instance(): self
  {
    return self::$instance ??= new self();
  }

  protected function configureCookieParams(): void
  {
    $c = $this->config;
    session_set_cookie_params([
      'lifetime' => ($c['lifetime'] ?? 120) * 60,
      'path'     => $c['path']      ?? '/',
      'domain'   => $c['domain']    ?? null,
      'secure'   => $c['secure']    ?? false,
      'httponly' => $c['http_only'] ?? true,
      'samesite' => $c['same_site'] ?? 'Lax',
    ]);
  }

  protected function configureSaveHandler(): void
  {
    $driver = $this->config['driver'] ?? 'database';

    switch ($driver) {
      case 'file':
        session_save_path($this->config['files'] ?? sys_get_temp_dir());
        break;

      case 'database':
        $pdo = Connection::getInstance();
        $handler = new DatabaseSessionHandler(
          $pdo,
          $this->config['table'] ?? 'sessions'
        );
        session_set_save_handler($handler, true);
        break;

      case 'cookie':
        $opts = [
          'lifetime'  => ($this->config['lifetime'] ?? 120) * 60,
          'path'      => $this->config['path']      ?? '/',
          'domain'    => $this->config['domain']    ?? null,
          'secure'    => $this->config['secure']    ?? false,
          'httponly'  => $this->config['http_only'] ?? true,
          'samesite'  => $this->config['same_site'] ?? 'Lax',
        ];
        $handler = new CookieSessionHandler(
          $this->config['cookie_name'] ?? 'pocketframe_session',
          $opts
        );
        session_set_save_handler($handler, true);
        break;

      case 'array':
        break;

      default:
        throw new \InvalidArgumentException("Unsupported session driver [{$driver}].");
    }
  }
}
