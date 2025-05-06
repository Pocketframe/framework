<?php

namespace Pocketframe\Cache;

class CacheEncryptor
{
  public static function encrypt($data): string
  {
    $key = base64_decode(config('cache.encryption.key'));
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt(serialize($data), 'AES-256-CBC', $key, 0, $iv);
    return base64_encode($iv . $encrypted);
  }

  public static function decrypt(string $payload)
  {
    $key = base64_decode(config('cache.encryption.key'));
    $decoded = base64_decode($payload);
    $iv = substr($decoded, 0, 16);
    $encrypted = substr($decoded, 16);
    $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    return unserialize($decrypted);
  }
}
