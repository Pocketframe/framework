<?php

namespace Pocketframe\Sessions\Storage;

use SessionHandlerInterface;

class CookieSessionHandler implements SessionHandlerInterface
{
  protected string $cookieName;
  protected array $cookieOptions;

  public function __construct(string $cookieName, array $cookieOptions = [])
  {
    $this->cookieName    = $cookieName;
    $this->cookieOptions = $cookieOptions;
  }

  public function open($savePath, $name): bool
  {
    return true;
  }

  public function close(): bool
  {
    return true;
  }

  public function read($id): string
  {
    if (!isset($_COOKIE[$this->cookieName])) {
      return '';
    }
    return base64_decode($_COOKIE[$this->cookieName]) ?: '';
  }

  public function write($id, $data): bool
  {
    setcookie(
      $this->cookieName,
      base64_encode($data),
      time() + ($this->cookieOptions['lifetime'] ?? 0),
      $this->cookieOptions['path'] ?? '/',
      $this->cookieOptions['domain'] ?? '',
      $this->cookieOptions['secure'] ?? false,
      $this->cookieOptions['http_only'] ?? true
    );
    return true;
  }

  public function destroy($id): bool
  {
    setcookie($this->cookieName, '', time() - 3600);
    return true;
  }

  public function gc($maxLifetime): int
  {
    // nothing to clean server-side
    return 0;
  }
}
