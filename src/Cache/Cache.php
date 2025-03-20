<?php

namespace Pocketframe\Cache;

/**
 * Class Cache
 *
 * Provides a simple in-memory caching mechanism.
 */
class Cache
{
  /** @var array Stores cached data with their expiration times. */
  protected static array $cache = [];

  /**
   * Stores data in the cache.
   *
   * @param string $key The cache key.
   * @param mixed $data The data to cache.
   * @param int $minutes The number of minutes to cache the data.
   */
  public static function put(string $key, $data, int $minutes = 60): void
  {
    $expiration = time() + ($minutes * 60);
    self::$cache[$key] = [
      'data' => $data,
      'expires_at' => $expiration,
    ];
  }

  /**
   * Retrieves data from the cache.
   *
   * @param string $key The cache key.
   * @return mixed|null The cached data, or null if not found or expired.
   */
  public static function get(string $key)
  {
    if (isset(self::$cache[$key])) {
      $cacheItem = self::$cache[$key];

      // Check if the cache item has expired
      if (time() < $cacheItem['expires_at']) {
        return $cacheItem['data'];
      }

      // Clear expired cache item
      self::forget($key);
    }

    return null;
  }

  /**
   * Checks if a cache key exists and is not expired.
   *
   * @param string $key The cache key.
   * @return bool True if the cache key exists and is valid, false otherwise.
   */
  public static function has(string $key): bool
  {
    if (isset(self::$cache[$key])) {
      return time() < self::$cache[$key]['expires_at'];
    }

    return false;
  }

  /**
   * Removes an item from the cache.
   *
   * @param string $key The cache key.
   */
  public static function forget(string $key): void
  {
    unset(self::$cache[$key]);
  }

  /**
   * Clears all cached data.
   */
  public static function flush(): void
  {
    self::$cache = [];
  }

  /**
   * Retrieves the expiration time for a cache key.
   *
   * @param string $key The cache key.
   * @return int|null The expiration timestamp, or null if the key does not exist.
   */
  public static function expiresAt(string $key): ?int
  {
    return self::$cache[$key]['expires_at'] ?? null;
  }
}
