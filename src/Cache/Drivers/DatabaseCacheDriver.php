<?php

namespace Pocketframe\Cache\Drivers;

use Pocketframe\Cache\CacheDriverInterface;
use DateTime;
use DateTimeZone;
use Pocketframe\PocketORM\Database\Connection;

class DatabaseCacheDriver implements CacheDriverInterface
{
  protected $table = 'cache';

  public function get(string $key)
  {
    $pdo = Connection::getInstance();
    $stmt = $pdo->prepare("SELECT value, expires_at FROM {$this->table} WHERE `key` = :key LIMIT 1");
    $stmt->execute([':key' => $key]);
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$row) {
      return null;
    }

    $now = new DateTime('now', new DateTimeZone('UTC'));
    $expiresAt = new DateTime($row['expires_at'], new DateTimeZone('UTC'));
    if ($expiresAt < $now) {
      $this->forget($key);
      return null;
    }

    return unserialize($row['value']);
  }

  public function put(string $key, $value, int $minutes = 60): void
  {
    $pdo = Connection::getInstance();
    $expiresAt = (new DateTime('now', new DateTimeZone('UTC')))
      ->modify("+{$minutes} minutes")
      ->format('Y-m-d H:i:s');
    $value = serialize($value);

    // Try update first, then insert if not exists (to be portable across DBs)
    $stmt = $pdo->prepare("UPDATE {$this->table} SET value = :value, expires_at = :expires_at WHERE `key` = :key");
    $stmt->execute([
      ':key' => $key,
      ':value' => $value,
      ':expires_at' => $expiresAt,
    ]);
    if ($stmt->rowCount() === 0) {
      $stmt = $pdo->prepare("INSERT INTO {$this->table} (`key`, `value`, `expires_at`) VALUES (:key, :value, :expires_at)");
      $stmt->execute([
        ':key' => $key,
        ':value' => $value,
        ':expires_at' => $expiresAt,
      ]);
    }
  }

  public function has(string $key): bool
  {
    $pdo = Connection::getInstance();
    $stmt = $pdo->prepare("SELECT expires_at FROM {$this->table} WHERE `key` = :key LIMIT 1");
    $stmt->execute([':key' => $key]);
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$row) {
      return false;
    }

    $now = new DateTime('now', new DateTimeZone('UTC'));
    $expiresAt = new DateTime($row['expires_at'], new DateTimeZone('UTC'));
    if ($expiresAt < $now) {
      $this->forget($key);
      return false;
    }

    return true;
  }

  public function forget(string $key): void
  {
    $pdo = Connection::getInstance();
    $stmt = $pdo->prepare("DELETE FROM {$this->table} WHERE `key` = :key");
    $stmt->execute([':key' => $key]);
  }

  public function flush(): void
  {
    $pdo = Connection::getInstance();
    $pdo->exec("DELETE FROM {$this->table}");
  }

  public function expiresAt(string $key): ?int
  {
    $pdo = Connection::getInstance();
    $stmt = $pdo->prepare("SELECT expires_at FROM {$this->table} WHERE `key` = :key LIMIT 1");
    $stmt->execute([':key' => $key]);
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$row) {
      return null;
    }

    $now = new DateTime('now', new DateTimeZone('UTC'));
    $expiresAt = new DateTime($row['expires_at'], new DateTimeZone('UTC'));
    if ($expiresAt < $now) {
      $this->forget($key);
      return null;
    }

    return $expiresAt->getTimestamp();
  }
}
