<?php

namespace Pocketframe\Cache;

use Pocketframe\Cache\Drivers\ArrayCacheDriver;
use Pocketframe\Cache\Drivers\DatabaseCacheDriver;
use Pocketframe\Cache\Drivers\MemoryCacheDriver;
use Pocketframe\Cache\Drivers\FileCacheDriver;
use Pocketframe\Cache\Drivers\RedisCacheDriver;

class CacheManager
{
  protected static ?CacheDriverInterface $driver = null;

  public static function setDriver(?string $type = null, array $config = []): void
  {
    $type = $type ?? config('cache.driver', 'file');

    switch ($type) {
      case 'file':
        $path = $config['path'] ?? config('cache.file.path');
        self::$driver = new FileCacheDriver($path);
        break;

      case 'memory':
        self::$driver = new MemoryCacheDriver();
        break;

      case 'array':
        self::$driver = new ArrayCacheDriver();
        break;

      case 'database':
        $connection = $config['connection'] ?? config('cache.database.connection');
        $table = $config['table'] ?? config('cache.database.table');
        self::$driver = new DatabaseCacheDriver($connection, $table);
        break;

      // case 'memcached':
      //   $servers = $config['servers'] ?? config('cache.memcached.servers');
      //   self::$driver = new MemcachedCacheDriver($servers);
      //   break;

      // case 'redis':
      //   $redisConfig = array_merge(config('cache.redis'), $config);
      //   self::$driver = new RedisCacheDriver($redisConfig);
      //   break;

      default:
        throw new \InvalidArgumentException("Unsupported cache driver: {$type}");
    }
  }

  public static function getDriver(): CacheDriverInterface
  {
    if (!self::$driver) {
      self::setDriver();
    }
    return self::$driver;
  }
}
