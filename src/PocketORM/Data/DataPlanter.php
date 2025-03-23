<?php

namespace Pocketframe\PocketORM\Data;

use Pocketframe\PocketORM\Database\Connection;

// same as seeder
abstract class DataPlanter
{
  abstract public function plant(): void;

  public static function run(): void
  {
    (new static())->plant();
  }

  protected function insertBatch(string $table, array $data): void
  {
    $columns = implode(', ', array_keys($data[0]));
    $placeholders = implode(', ', array_fill(0, count($data[0]), '?'));

    $stmt = Connection::getInstance()->prepare(
      "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})"
    );

    foreach ($data as $row) {
      $stmt->execute(array_values($row));
    }
  }
}
