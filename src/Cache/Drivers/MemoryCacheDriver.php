<?php

namespace Pocketframe\Cache\Drivers;

use Pocketframe\Cache\CacheDriverInterface;

class MemoryCacheDriver implements CacheDriverInterface
{
  protected array $cache = [];

  public function get(string $key)
  {
    if (isset($this->cache[$key]) && time() < $this->cache[$key]['expires_at']) {
      return $this->cache[$key]['data'];
    }

    unset($this->cache[$key]);
    return null;
  }

  public function put(string $key, $value, int $minutes = 60): void
  {
    $this->cache[$key] = [
      'data' => $value,
      'expires_at' => time() + $minutes * 60,
    ];
  }

  public function has(string $key): bool
  {
    return isset($this->cache[$key]) && time() < $this->cache[$key]['expires_at'];
  }

  public function forget(string $key): void
  {
    unset($this->cache[$key]);
  }

  public function flush(): void
  {
    $this->cache = [];
  }

  public function expiresAt(string $key): null
  {
    return null;
  }
}
