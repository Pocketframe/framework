<?php

namespace Pocketframe\Cache;

interface CacheDriverInterface
{
  public function get(string $key);
  public function put(string $key, $value, int $minutes = 60): void;
  public function has(string $key): bool;
  public function forget(string $key): void;
  public function flush(): void;
  public function expiresAt(string $key): ?int;
}
