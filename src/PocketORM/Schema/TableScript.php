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


  /**
   * Alters a table using DBAL for complex diffs.
   *
   * @param string   $table         The name of the table to alter.
   * @param callable $tableTemplate A callback that applies changes to a TableBuilder.
   */
  protected function alterTableWithDbal(string $table, callable $tableTemplate): void
  {
    $conn = Connection::getInstance()->getDoctrineConnection();
    $schemaManager = $conn->createSchemaManager(); // For DBAL 3; in older versions use ->getSchemaManager()

    // Get current table schema
    $currentSchema = $schemaManager->introspectSchema();
    $currentTable = $currentSchema->getTable($table);

    // Create a clone of the current schema to modify as desired.
    $newSchema = clone $currentSchema;
    $newTable = $newSchema->getTable($table);

    // Use your TableBuilder to apply modifications.
    // You could either build a diff mechanism in your TableBuilder
    // or simply call the closure to modify a blueprint object that
    // you then apply on the DBAL table.
    $blueprint = new TableBuilder($table);
    $tableTemplate($blueprint);

    // Here, you need to apply the changes from the blueprint on the DBAL table schema.
    // For illustration, let’s assume we want to add a new column if it does not exist.
    // (Extend this with proper diff logic as needed.)
    $newColumnName = 'example_column';
    if (! $newTable->hasColumn($newColumnName)) {
      // Create a new column object. You can fine-tune the column options.
      $newTable->addColumn($newColumnName, 'string', ['length' => 255]);
    }

    // Get the differences as an instance of TableDiff
    $comparator = new \Doctrine\DBAL\Schema\Comparator();
    $tableDiff = $comparator->diffTable($currentTable, $newTable);

    if ($tableDiff) {
      // Generate SQL queries from the table diff
      $platform = $conn->getDatabasePlatform();
      $alterSql = $platform->getAlterTableSQL($tableDiff);
      foreach ($alterSql as $sql) {
        $conn->executeStatement($sql);
      }
    }
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
