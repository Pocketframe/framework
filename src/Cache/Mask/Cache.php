<?php

namespace Pocketframe\Cache\Mask;

use Pocketframe\Cache\CacheManager;

class Cache
{
  protected static $driver;

  public static function get(string $key)
  {
    return CacheManager::getDriver()->get($key);
  }

  public static function put(string $key, $value, int $minutes = 60): void
  {
    CacheManager::getDriver()->put($key, $value, $minutes);
  }

  public static function has(string $key): bool
  {
    return CacheManager::getDriver()->has($key);
  }

  public static function forget(string $key): void
  {
    CacheManager::getDriver()->forget($key);
  }

  public static function flush(): void
  {
    CacheManager::getDriver()->flush();
  }

  public static function remember(string $key, int $minutes, callable $callback)
  {
    if (self::has($key)) {
      return self::get($key);
    }

    $value = $callback();
    self::put($key, $value, $minutes);
    return $value;
  }

  public static function rememberForever(string $key, callable $callback)
  {
    return self::remember($key, 52560000, $callback); // ~100 years
  }

  public static function expiresAt(string $key): ?int
  {
    return null;
  }
}
