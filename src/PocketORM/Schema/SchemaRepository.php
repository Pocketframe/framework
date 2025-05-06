<?php

namespace Pocketframe\PocketORM\Schema;

use PDO;
use Pocketframe\Essentials\Utilities\StringUtils;
use Pocketframe\PocketORM\QueryEngine\QueryEngine;
use Pocketframe\Exceptions\Database\QueryException;
use Pocketframe\PocketORM\Database\Connection;

class SchemaRepository
{
  protected array $appliedSchemas = [];

  public function getPendingSchemas(): array
  {
    $applied = $this->getAppliedSchemaNames();
    $all = glob(database_path('schemas/*.php'));

    return array_filter(
      $all,
      fn($path) => !in_array(basename($path), $applied)
    );
  }

  protected function getAppliedSchemaNames(): array
  {
    try {
      // First check if schema table exists
      $exists = (new QueryEngine('pocket_schemas'))
        ->selectRaw('1')
        ->limit(1)
        ->get()
        ->count() > 0;

      if (!$exists) return [];

      return (new QueryEngine('pocket_schemas'))
        ->select(['schema_name'])
        ->get()
        ->pluck('schema_name');
    } catch (QueryException) {
      return [];
    }
  }

  public function getLastAppliedSchema(): ?SchemaRecord
  {
    try {
      $result = (new QueryEngine('pocket_schemas'))
        ->select(['schema_name', 'applied_at'])
        ->orderBy('batch', 'DESC')
        ->orderBy('applied_at', 'DESC')
        ->limit(1)
        ->first();

      if (!$result) return null;

      return new SchemaRecord(
        name: $result->schema_name,
        path: database_path("schemas/{$result->schema_name}"),
        appliedAt: $result->applied_at
      );
    } catch (QueryException) {
      return null;
    }
  }

  public function getNextBatchNumber(): int
  {
    try {
      $result = (new QueryEngine('pocket_schemas'))
        ->selectRaw('COALESCE(MAX(batch), 0) as last_batch')
        ->first();

      return (int)$result->last_batch + 1;
    } catch (QueryException) {
      return 1;
    }
  }


  public function recordSchema(string $schemaName): void
  {
    (new QueryEngine('pocket_schemas'))->insert([
      'schema_name' => $schemaName,
      'applied_at' => StringUtils::now(),
      'batch' => $this->getNextBatchNumber()
    ]);
  }

  public function getLastBatch(): array
  {
    try {
      $maxBatch = (new QueryEngine('pocket_schemas'))
        ->selectRaw('MAX(batch) as max_batch')
        ->first()
        ->max_batch;

      return (new QueryEngine('pocket_schemas'))
        ->select(['schema_name', 'applied_at', 'batch'])
        ->where('batch', '=', $maxBatch)
        ->get()
        ->all();
    } catch (QueryException) {
      return [];
    }
  }


  public function schemaTableExists(): bool
  {
    try {
      (new QueryEngine('pocket_schemas'))
        ->selectRaw('1')
        ->limit(1)
        ->get();
      return true;
    } catch (QueryException) {
      return false;
    }
  }

  public function deleteSchemaRecord(string $schemaName): void
  {
    (new QueryEngine('pocket_schemas'))
      ->where('schema_name', '=', $schemaName)
      ->delete();
  }

  public function deleteAllSchemaRecords(): void
  {
    (new QueryEngine('pocket_schemas'))
      ->delete();
  }


  public function dropAllTables(): void
  {
    $connection = Connection::getInstance();
    $driver = config('database.driver', 'mysql');

    // Disable foreign key constraints
    match ($driver) {
      'mysql' => $connection->exec('SET FOREIGN_KEY_CHECKS=0;'),
      'sqlite' => $connection->exec('PRAGMA foreign_keys = OFF;'),
      'pgsql' => $connection->exec('SET CONSTRAINTS ALL DEFERRED;'),
      default => null
    };

    // Get all tables
    $tables = match ($driver) {
      'mysql' => $connection->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN),
      'sqlite' => $connection->query("SELECT name FROM sqlite_master WHERE type='table'")
        ->fetchAll(PDO::FETCH_COLUMN),
      default => []
    };

    // Drop all tables including pocket_schemas
    foreach ($tables as $table) {
      $connection->exec("DROP TABLE IF EXISTS `{$table}`");
    }

    // Re-enable constraints
    match ($driver) {
      'mysql' => $connection->exec('SET FOREIGN_KEY_CHECKS=1;'),
      'sqlite' => $connection->exec('PRAGMA foreign_keys = ON;'),
      default => null
    };

    $this->appliedSchemas = [];
  }
}
