<?php

namespace Pocketframe\Console\Commands;

use PDO;
use PDOException;
use Pocketframe\Contracts\CommandInterface;
use Pocketframe\PocketORM\Schema\SchemaRecord;
use Pocketframe\PocketORM\Schema\SchemaRepository;
use Throwable;

class SchemaCommand implements CommandInterface
{
  protected array $args;
  protected SchemaRepository $repository;

  public function __construct(array $args)
  {
    $this->args = $args;
    $this->repository = new SchemaRepository();
  }

  public function handle(): void
  {
    $action = $this->args[0] ?? null;

    try {
      match ($action) {
        'apply'     => $this->applySchemas(),
        'rollback'  => $this->rollbackLastSchema(),
        'fresh'     => $this->freshSchemas(),
        default     => $this->showUsage(),
      };
    } catch (Throwable $e) {
      $this->handleGenericError($e);
    }
  }

  protected function applySchemas(bool $force = false): void
  {
    try {
      // Always apply system schema first
      $systemSchema = database_path('schemas/0000_00_00_000000_pocket_schemas_table.php');
      require_once $systemSchema;
      $className = $this->getClassNameFromPath($systemSchema);
      (new $className())->up();
      $this->repository->recordSchema(basename($systemSchema));
      echo "💪 System schema initialized\n";

      // Get remaining schemas
      $pendingSchemas = $force
        ? $this->getAllSchemaFiles()
        : $this->repository->getPendingSchemas();

      foreach ($pendingSchemas as $schemaPath) {
        // Skip already processed system schema
        if (str_contains($schemaPath, '0000_00_00_000000')) continue;

        try {
          require_once $schemaPath;
          $className = $this->getClassNameFromPath($schemaPath);
          $schema = new $className();

          echo "🔄 Applying schema: " . basename($schemaPath) . "\n";
          $schema->up();

          $this->repository->recordSchema(basename($schemaPath));
          echo "💪 Successfully applied: " . basename($schemaPath) . "\n";
        } catch (PDOException $e) {
          $this->handleSchemaError($e, $schemaPath);
          exit(1);
        }
      }
    } catch (Throwable $e) {
      $this->handleGenericError($e);
      exit(1);
    }
  }


  protected function rollbackLastSchema(): void
  {
    try {
      if (!$this->repository->schemaTableExists()) {
        echo "❌ Error: Migration history table missing\n";
        echo "💡 Tip: Run initial setup with `php pocket schema:apply` first\n";
        exit(1);
      }

      $batch = $this->repository->getLastBatch();

      if (empty($batch)) {
        echo "ℹ️ No schemas to rollback.\n";
        return;
      }

      // Get first batch number safely
      $firstBatch = $batch[0]->batch ?? $batch[0]['batch'] ?? null;
      if (!$firstBatch) {
        echo "❌ Invalid batch format\n";
        return;
      }

      echo "⏪ Rolling back batch #$firstBatch\n";

      foreach (array_reverse($batch) as $schema) {
        $this->rollbackSingleSchema((object)$schema);
      }
    } catch (\PDOException $e) {
      $this->handleSchemaError($e, 'rollback operation');
      exit(1);
    }
  }


  private function rollbackSingleSchema(object $schema): void
  {
    if (!property_exists($schema, 'schema_name')) {
      echo "❌ Invalid schema record format\n";
      return;
    }

    $path = database_path("schemas/{$schema->schema_name}");

    if (!file_exists($path)) {
      echo "❌ Missing migration file: {$schema->schema_name}\n";
      return;
    }

    require_once $path;
    $className = $this->getClassNameFromPath($path);

    try {
      (new $className)->down();
      $this->repository->deleteSchemaRecord($schema->schema_name);
      echo "↩️ Rolled back: {$schema->schema_name}\n";
    } catch (\PDOException $e) {
      echo "❌ Failed to rollback: {$schema->schema_name}\n";
      throw $e;
    }
  }


  private function handleSystemSchemaRollback(SchemaRecord $schema): void
  {
    echo "⚠️ Rolling back system schema - this will remove migration tracking!\n";

    require_once $schema->path;
    $className = $this->getClassNameFromPath($schema->path);

    // Run down() first before deleting record
    try {
      (new $className)->down();
      $this->repository->deleteSchemaRecord($schema->name);
      echo "↩️ Rolled back system schema: {$schema->name}\n";
      echo "💡 Warning: Migration history tracking is now disabled!\n";
    } catch (PDOException $e) {
      echo "❌ Critical error: Failed to rollback system schema\n";
      echo "   Error: " . $e->getMessage() . "\n";
      exit(1);
    }
  }

  protected function freshSchemas(): void
  {
    try {
      echo "🔄 Nuclear database reset...\n";

      // 1. Drop ALL tables
      $this->repository->dropAllTables();

      // 2. Force-apply all migrations without checking records
      $this->applySchemas(true);

      echo "✨ Database completely erased and rebuilt!\n";
    } catch (PDOException $e) {
      $this->handleSchemaError($e, 'fresh operation');
      exit(1);
    }
  }

  private function getAllSchemaFiles(): array
  {
    return glob(database_path('schemas/*.php'));
  }

  private function dropAllTablesManually(PDO $connection, string $driver): void
  {
    // Disable foreign keys
    match ($driver) {
      'mysql' => $connection->exec('SET FOREIGN_KEY_CHECKS=0;'),
      'sqlite' => $connection->exec('PRAGMA foreign_keys = OFF;'),
      default => null
    };

    // Get tables
    $tables = match ($driver) {
      'mysql' => $connection->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN),
      'sqlite' => $connection->query("SELECT name FROM sqlite_master WHERE type='table'")
        ->fetchAll(PDO::FETCH_COLUMN),
      default => []
    };

    // Drop tables
    foreach ($tables as $table) {
      $connection->exec("DROP TABLE IF EXISTS `{$table}`");
    }

    // Re-enable constraints
    match ($driver) {
      'mysql' => $connection->exec('SET FOREIGN_KEY_CHECKS=1;'),
      'sqlite' => $connection->exec('PRAGMA foreign_keys = ON;'),
      default => null
    };
  }

  private function handleSchemaError(PDOException $e, string $context): void
  {
    $errorInfo = $e->errorInfo ?? [];
    $errorCode = $errorInfo[1] ?? $e->getCode();

    $message = match ($errorCode) {
      1050 => "Table already exists ({$context})",
      1146 => "Missing system table - run initial setup first",
      1217 => "Foreign key constraint violation during rollback",
      1451 => "Cannot delete or update parent record",
      default => $e->getMessage()
    };

    echo "\n❌ Database Error: {$message}";
    echo "\n   Error Code: {$errorCode}";
    echo "\n   Context: {$context}";
    echo "\n💡 Tip: Check database consistency and migration order\n\n";
  }

  private function handleMigrationError(Throwable $e, string $schemaPath): void
  {
    echo "\n❌ Migration Error: " . $e->getMessage();
    echo "\n   File: " . basename($schemaPath);
    echo "\n💡 Tip: Check migration file for syntax errors\n\n";
  }

  private function handleGenericError(Throwable $e): void
  {
    echo "\n🚨 Unexpected Error: " . $e->getMessage();
    echo "\n  File: " . $e->getFile() . " (Line: " . $e->getLine() . ")";
    echo "\n💡 Tip: Check application logs for more details\n\n";
  }

  protected function showUsage(): void
  {
    echo "Usage:\n";
    echo "  php pocket schema:apply\n";
    echo "  php pocket schema:rollback\n";
    echo "  php pocket schema:fresh\n";
  }

  private function getClassNameFromPath(string $path): string
  {
    $filename = basename($path, '.php');

    // Remove "_table" suffix if present
    $filename = preg_replace('/_table$/', '', $filename);

    // Split filename into parts and remove timestamp (first 4 segments)
    $parts = explode('_', $filename);
    $nameParts = array_slice($parts, 4); // Remove YYYY_MM_DD_HHMMSS

    // Convert to PascalCase and prepend namespace
    $className = str_replace(' ', '', ucwords(implode(' ', $nameParts)));

    return 'Database\\Schemas\\' . $className;
  }
}
