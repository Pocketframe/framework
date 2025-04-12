<?php

namespace Pocketframe\PocketORM\Schema;

use Pocketframe\PocketORM\Database\Connection;

// same as migration
abstract class TableScript
{

  /**
   * @var array
   */
  protected array $postCommands = [];

  /**
   * The up method is called when the migration is applied.
   *
   * @return void
   */
  abstract public function up(): void;

  /**
   * The down method is called when the migration is rolled back.
   *
   * @return void
   */
  abstract public function down(): void;

  /**
   * Creates a new table.
   *
   * @param string $table The name of the table to create
   * @param callable $tableTemplate A callback that defines the table structure
   * @return void
   */
  protected function createTable(string $table, callable $tableTemplate): void
  {
    $tableBuilder = new TableBuilder($table);
    $tableTemplate($tableBuilder);

    Connection::getInstance()->exec(
      $tableBuilder->compileCreate()
    );

    foreach ($tableBuilder->getPostCommands() as $command) {
      Connection::getInstance()->exec($command);
    }
  }

  protected function alterTable(string $table, callable $tableTemplate): void
  {
    $tableBuilder = new TableBuilder($table);
    $tableTemplate($tableBuilder);

    Connection::getInstance()->exec(
      $tableBuilder->compileAlter()
    );
  }

  protected function dropTable(string $table): void
  {
    Connection::getInstance()->exec("DROP TABLE IF EXISTS {$table}");
  }

  public function getPostCommands(): array
  {
    return $this->postCommands;
  }
}
