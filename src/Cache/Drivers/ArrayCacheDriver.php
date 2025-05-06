<?php

namespace Pocketframe\Cache\Drivers;

use Pocketframe\Cache\CacheDriverInterface;

class ArrayCacheDriver implements CacheDriverInterface
{
  public function get(string $key)
  {
    return null;
  }

  public function put(string $key, $value, int $minutes = 60): void {}

  public function has(string $key): bool
  {
    return false;
  }

  public function forget(string $key): void {}

  public function flush(): void {}

  public function expiresAt(string $key): ?int
  {
    return null;
  }
}
