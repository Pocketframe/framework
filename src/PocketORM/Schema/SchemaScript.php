<?php

namespace Pocketframe\PocketORM\Schema;

use Pocketframe\PocketORM\Database\Connection;

// same as migration
abstract class SchemaScript
{
  abstract public function up(): void;
  abstract public function down(): void;

  protected function createTable(string $table, callable $blueprint): void
  {
    $tableBuilder = new TableBuilder($table);
    $blueprint($tableBuilder);

    Connection::getInstance()->exec(
      $tableBuilder->compileCreate()
    );
  }

  protected function alterTable(string $table, callable $blueprint): void
  {
    $tableBuilder = new TableBuilder($table);
    $blueprint($tableBuilder);

    Connection::getInstance()->exec(
      $tableBuilder->compileAlter()
    );
  }

  protected function dropTable(string $table): void
  {
    Connection::getInstance()->exec("DROP TABLE IF EXISTS {$table}");
  }
}
