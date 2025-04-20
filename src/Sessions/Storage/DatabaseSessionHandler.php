<?php

namespace Pocketframe\Sessions\Storage;

use PDO;
use SessionHandlerInterface;

class DatabaseSessionHandler implements SessionHandlerInterface
{
  protected PDO $pdo;
  protected string $table;
  protected string $colId;
  protected string $colData;
  protected string $colUserId;
  protected string $colIpAddress;
  protected string $colUserAgent;
  protected string $colTime;

  public function __construct(
    PDO $pdo,
    string $table,
    string $colId = 'id',
    string $colData = 'payload',
    string $colUserId = 'user_id',
    string $colIpAddress = 'ip_address',
    string $colUserAgent = 'user_agent',
    string $colTime = 'last_activity'
  ) {
    $this->pdo     = $pdo;
    $this->table   = $table;
    $this->colId   = $colId;
    $this->colData = $colData;
    $this->colUserId = $colUserId;
    $this->colIpAddress = $colIpAddress;
    $this->colUserAgent = $colUserAgent;
    $this->colTime = $colTime;
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
    $stmt = $this->pdo->prepare(
      "SELECT {$this->colData} FROM {$this->table} WHERE {$this->colId} = :id"
    );
    $stmt->execute(['id' => $id]);
    return (string) ($stmt->fetchColumn() ?: '');
  }

  public function write($id, $data): bool
  {
    $time       = time();
    $user_id    = session('user_id');
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $sql = "REPLACE INTO {$this->table}
                ({$this->colId}, {$this->colData}, {$this->colUserId}, {$this->colIpAddress}, {$this->colUserAgent}, {$this->colTime})
                VALUES (:id, :data, :user_id, :ip_address, :user_agent, :time)";
    $stmt = $this->pdo->prepare($sql);
    return $stmt->execute(['id' => $id, 'data' => $data, 'user_id' => $user_id, 'ip_address' => $ip_address, 'user_agent' => $user_agent, 'time' => $time]);
  }

  public function destroy($id): bool
  {
    $stmt = $this->pdo->prepare(
      "DELETE FROM {$this->table} WHERE {$this->colId} = :id"
    );
    return $stmt->execute(['id' => $id]);
  }

  public function gc($maxLifetime): int|false
  {
    $past = time() - $maxLifetime;
    $stmt = $this->pdo->prepare(
      "DELETE FROM {$this->table} WHERE {$this->colTime} < :past"
    );
    return $stmt->execute(['past' => $past]);
  }
}
