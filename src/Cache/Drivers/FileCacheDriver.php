<?php

namespace Pocketframe\Cache\Drivers;

use Pocketframe\Cache\CacheDriverInterface;
use Pocketframe\Cache\CacheEncryptor;

class FileCacheDriver implements CacheDriverInterface
{
  protected string $cacheDir;

  public function __construct(string $cacheDir = __DIR__ . '/../../store/cache')
  {
    $this->cacheDir = rtrim($cacheDir, '/');
    if (!is_dir($this->cacheDir)) {
      mkdir($this->cacheDir, 0755, true);
    }
  }

  protected function filePath(string $key): string
  {
    return $this->cacheDir . '/' . sha1($key) . '.json';
  }

  public function get(string $key)
  {
    $filePath = $this->filePath($key);

    if (!file_exists($filePath)) return null;

    $payload = unserialize(file_get_contents($filePath));

    if (time() >= $payload['expires_at']) {
      unlink($filePath);
      return null;
    }

    $data = $payload['data'];

    if (config('cache.encryption.enabled')) {
      $data = CacheEncryptor::decrypt($data);
    }

    return $data;
  }

  public function put(string $key, $value, int $minutes = 60): void
  {
    if (config('cache.encryption.enabled')) {
      $value = CacheEncryptor::encrypt($value);
    }

    $filePath = $this->filePath($key);
    $payload = [
      'data' => $value,
      'expires_at' => time() + ($minutes * 60),
    ];

    file_put_contents($filePath, json_encode($payload));
  }

  public function has(string $key): bool
  {
    $path = $this->filePath($key);
    if (!file_exists($path)) return false;

    $data = json_decode(file_get_contents($path), true);
    if (time() >= $data['expires_at']) {
      unlink($path);
      return false;
    }

    return true;
  }

  public function forget(string $key): void
  {
    $path = $this->filePath($key);
    if (file_exists($path)) {
      unlink($path);
    }
  }

  public function flush(): void
  {
    foreach (glob($this->cacheDir . '/*.json') as $file) {
      unlink($file);
    }
  }

  public function expiresAt(string $key): ?int
  {
    return null;
  }
}
