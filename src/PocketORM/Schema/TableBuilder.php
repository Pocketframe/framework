<?php

namespace Pocketframe\PocketORM\Schema;

use Pocketframe\Essentials\Utilities\StringUtils;

class TableBuilder
{
  private string $table;
  private array $columns = [];
  private array $indexes = [];
  private array $drops = [];
  private string $lastColumnName;
  private array $foreignKeys = [];
  private ?string $lastFkConstraint = null;
  private string $engine = 'InnoDB';
  private string $charset = 'utf8mb4';
  private string $collation = 'utf8mb4_unicode_ci';
  private array $postCommands = [];
  private string $driver;

  public function __construct(string $table)
  {
    $this->table = $table;
    $this->driver = config('database.driver', 'mysql');
  }

  /**
   * Get the appropriate identifier quote character
   */
  private function quoteIdentifier(string $name): string
  {
    return $this->driver === 'sqlite' ? '"' . $name . '"' : '`' . $name . '`';
  }

  /**
   * Check if using SQLite
   */
  private function isSQLite(): bool
  {
    return $this->driver === 'sqlite';
  }

  /**
   * Adds an auto-incrementing primary key column named 'id'.
   *
   * @return self
   */
  public function id(): self
  {
    $this->bigIncrements('id');
    return $this;
  }

  /**
   * @method bigIncrements
   *
   * Adds a BIGINT column with auto-increment and primary key constraints.
   *
   * @param string $name The name of the column.
   * @return self
   */
  public function bigIncrements(string $name): self
  {
    if ($this->isSQLite()) {
      $this->columns[] = "{$this->quoteIdentifier($name)} INTEGER PRIMARY KEY AUTOINCREMENT";
    } else {
      $this->columns[] = "{$this->quoteIdentifier($name)} BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY";
    }
    $this->lastColumnName = $name;
    return $this;
  }

  /**
   * @method bigInteger
   *
   * Adds a BIGINT column.
   *
   * @param string $name The name of the column.
   * @return self
   */
  public function bigInteger(string $name): self
  {
    $this->columns[] = "`{$name}` BIGINT";
    $this->lastColumnName = $name;
    return $this;
  }

  /**
   * @method binary
   *
   * Adds a BINARY column.
   *
   * @param string $name The name of the column.
   * @return self
   */
  public function binary(string $name): self
  {
    $this->columns[] = "`{$name}` BINARY";
    $this->lastColumnName = $name;
    return $this;
  }

  /**
   * @method boolean
   *
   * Adds a boolean column.
   *
   * Adds a column to the table that allows storing boolean values.
   *
   * @param string $name The name of the column.
   * @return self
   */
  public function boolean(string $name): self
  {
    $type = $this->isSQLite() ? 'INTEGER' : 'BOOLEAN';
    $this->columns[] = "{$this->quoteIdentifier($name)} {$type}";
    $this->lastColumnName = $name;
    return $this;
  }

  /**
   * @method char
   *
   * Adds a char column.
   *
   * Adds a column to the table that allows storing characters.
   *
   * @param string $name The name of the column.
   * @param int $length The length of the column.
   * @return self
   */
  public function char(string $name, int $length): self
  {
    $this->columns[] = "`{$name}` CHAR({$length})";
    $this->lastColumnName = $name;
    return $this;
  }

  /**
   * @method date
   *
   * Adds a date column.
   *
   * Adds a column to the table that allows storing dates.
   *
   * @param string $name The name of the column.
   * @return self
   */
  public function date(string $name): self
  {
    $this->columns[] = "`{$name}` DATE";
    $this->lastColumnName = $name;
    return $this;
  }

  /**
   * @method dateTime
   *
   * Adds a datetime column.
   *
   * Adds a column to the table that allows storing dates and times.
   *
   * @param string $name The name of the column.
   * @return self
   */
  public function dateTime(string $name): self
  {
    $this->columns[] = "`{$name}` DATETIME";
    $this->lastColumnName = $name;
    return $this;
  }

  /**
   * @method decimal
   *
   * Adds a decimal column.
   *
   * Adds a column to the table that allows storing decimal numbers.
   *
   * @param string $name The name of the column.
   * @param int $precision The precision of the column.
   * @param int $scale The scale of the column.
   * @return self
   */
  public function decimal(string $name, int $precision = 10, int $scale = 2): self
  {
    $this->columns[] = "`{$name}` DECIMAL({$precision}, {$scale})";
    $this->lastColumnName = $name;
    return $this;
  }

  /**
   * @method double
   *
   * Adds a double column.
   *
   * Adds a column to the table that allows storing double precision numbers.
   *
   * @param string $name The name of the column.
   * @param int $totalDigits The total number of digits.
   * @param int $decimalPlaces The number of decimal places.
   * @return self
   */
  public function double(string $name, int $totalDigits = 15, int $decimalPlaces = 8): self
  {
    $this->columns[] = "`{$name}` DOUBLE({$totalDigits}, {$decimalPlaces})";
    $this->lastColumnName = $name;
    return $this;
  }

  /**
   * @method enum
   *
   * Adds an enum column.
   *
   * Adds a column to the table that allows storing enum values.
   *
   * @param string $name The name of the column.
   * @param array $allowedValues The allowed values for the column.
   * @return self
   */
  public function enum(string $name, array $allowedValues): self
  {
    if ($this->isSQLite()) {
      $this->columns[] = "{$this->quoteIdentifier($name)} TEXT";
      $values = implode("','", $allowedValues);
      $this->columns[] = "CHECK ({$this->quoteIdentifier($name)} IN ('{$values}'))";
    } else {
      $this->columns[] = "{$this->quoteIdentifier($name)} ENUM('" . implode("','", $allowedValues) . "')";
    }
    $this->lastColumnName = $name;
    return $this;
  }

  /**
   * @method float
   *
   * Adds a float column.
   *
   * Adds a column to the table that allows storing float numbers.
   *
   * @param string $name The name of the column.
   * @param int $totalDigits The total number of digits.
   * @param int $decimalPlaces The number of decimal places.
   * @return self
   */
  public function float(string $name, int $totalDigits = 10, int $decimalPlaces = 5): self
  {
    $this->columns[] = "`{$name}` FLOAT({$totalDigits}, {$decimalPlaces})";
    $this->lastColumnName = $name;
    return $this;
  }

  /**
   * @method increments
   *
   * Adds an integer column with auto-increment and primary key constraints.
   *
   * Adds a column to the table that allows storing integers.
   * Sets the column as the primary key with auto-increment.
   *
   * @param string $name The name of the column.
   * @return self
   */
  public function increments(string $name): self
  {
    $this->columns[] = "`{$name}` INTEGER UNSIGNED AUTO_INCREMENT PRIMARY KEY";
    $this->lastColumnName = $name;
    return $this;
  }

  /**
   * @method integer
   *
   * Adds an integer column.
   *
   * Adds a column to the table that allows storing integers.
   *
   * @param string $name The name of the column.
   * @return self
   */
  public function integer(string $name): self
  {
    $this->columns[] = "`{$name}` INTEGER";
    $this->lastColumnName = $name;
    return $this;
  }

  /**
   * @method json
   *
   * Adds a json column.
   *
   * Adds a column to the table that allows storing JSON data.
   *
   * @param string $name The name of the column.
   * @return self
   */
  public function json(string $name): self
  {
    $this->columns[] = "`{$name}` JSON";
    $this->lastColumnName = $name;
    return $this;
  }

  /**
   * @method jsonb
   *
   * Adds a jsonb column.
   *
   * Adds a column to the table that allows storing JSONB data.
   *
   * @param string $name The name of the column.
   * @return self
   */
  public function jsonb(string $name): self
  {
    $this->columns[] = "`{$name}` JSONB";
    $this->lastColumnName = $name;
    return $this;
  }

  /**
   * @method longText
   *
   * Adds a long text column.
   *
   * Adds a column to the table that allows storing long text.
   *
   * @param string $name The name of the column.
   * @return self
   */
  public function longText(string $name): self
  {
    $this->columns[] = "`{$name}` LONGTEXT";
    $this->lastColumnName = $name;
    return $this;
  }

  /**
   * @method mediumIncrements
   *
   * Adds a mediumint column with auto-increment and primary key constraints.
   *
   * Adds a column to the table that allows storing mediumint numbers.
   * Sets the column as the primary key with auto-increment.
   *
   * @param string $name The name of the column.
   * @return self
   */
  public function mediumIncrements(string $name): self
  {
    $this->columns[] = "`{$name}` MEDIUMINT UNSIGNED AUTO_INCREMENT PRIMARY KEY";
    $this->lastColumnName = $name;
    return $this;
  }

  /**
   * @method mediumInteger
   *
   * Adds a mediumint column.
   *
   * Adds a column to the table that allows storing mediumint numbers.
   *
   * @param string $name The name of the column.
   * @return self
   */
  public function mediumInteger(string $name): self
  {
    $this->columns[] = "`{$name}` MEDIUMINT";
    $this->lastColumnName = $name;
    return $this;
  }

  /**
   * @method mediumText
   *
   * Adds a medium text column.
   *
   * Adds a column to the table that allows storing medium text.
   *
   * @param string $name The name of the column.
   * @return self
   */
  public function mediumText(string $name): self
  {
    $this->columns[] = "{$name} MEDIUMTEXT";
    $this->lastColumnName = $name;
    return $this;
  }

  /**
   * @method morphs
   *
   * Adds a morphs column.
   *
   * Adds a column to the table that allows storing morphs data.
   *
   * @param string $name The name of the column.
   * @return self
   */
  public function morphs(string $name): self
  {
    $this->columns[] = "{$name} INTEGER UNSIGNED";
    $this->columns[] = "{$name}_type VARCHAR(255)";
    $this->lastColumnName = $name;
    return $this;
  }

  /**
   * @method nullableTimestamps
   *
   * Adds nullable timestamps columns.
   *
   * Adds two columns to the table that allows storing created_at and updated_at timestamps.
   * Sets the columns as nullable.
   *
   * @return self
   */
  public function nullableTimestamps(): self
  {
    $this->timestamps('created_at', 'updated_at', true);
    return $this;
  }

  /**
   * @method smallIncrements
   *
   * Adds a smallint column with auto-increment and primary key constraints.
   *
   * Adds a column to the table that allows storing smallint numbers.
   * Sets the column as the primary key with auto-increment.
   *
   * @param string $name The name of the column.
   * @return self
   */
  public function smallIncrements(string $name): self
  {
    $this->columns[] = "{$name} SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY";
    $this->lastColumnName = $name;
    return $this;
  }

  /**
   * @method smallInteger
   * Adds a smallint column.
   *
   * Adds a column to the table that allows storing smallint numbers.
   *
   * @param string $name The name of the column.
   * @return self
   */
  public function smallInteger(string $name): self
  {
    $this->columns[] = "{$name} SMALLINT";
    $this->lastColumnName = $name;
    return $this;
  }

  /**
   * @method string
   * Adds a string column.
   *
   * Adds a column to the table that allows storing strings.
   *
   * @param string $name The name of the column.
   * @param int $length The length of the column.
   * @return self
   */
  public function string(string $name, int $length = 255): self
  {
    $type = $this->isSQLite() ? 'TEXT' : "VARCHAR({$length})";
    $this->columns[] = "{$this->quoteIdentifier($name)} {$type}";
    $this->lastColumnName = $name;
    return $this;
  }

  /**
   * @method text
   * Adds a text column.
   *
   * Adds a column to the table that allows storing text.
   *
   * @param string $name The name of the column.
   * @return self
   */
  public function text(string $name): self
  {
    $this->columns[] = "{$name} TEXT";
    $this->lastColumnName = $name;
    return $this;
  }

  /**
   * @method time
   * Adds a time column.
   *
   * Adds a column to the table that allows storing time.
   *
   * @param string $name The name of the column.
   * @return self
   */
  public function time(string $name, int $precision = 0): self
  {
    $this->columns[] = "`{$name}` TIME" . ($precision ? "({$precision})" : '');
    $this->lastColumnName = $name;
    return $this;
  }

  /**
   * @method timeTz
   * Adds a time with timezone column.
   *
   * Adds a column to the table that allows storing time with timezone.
   *
   * @param string $name The name of the column.
   * @return self
   */
  public function timeTz(string $name, int $precision = 0): self
  {
    $this->columns[] = "`{$name}` TIME" . ($precision ? "({$precision})" : '') . " WITH TIME ZONE";
    $this->lastColumnName = $name;
    return $this;
  }

  /**
   * @method timestamp
   * Adds a timestamp column.
   *
   * Adds a column to the table that allows storing timestamp.
   *
   * @param string $name The name of the column.
   * @return self
   */
  public function timestamp(string $name): self
  {
    $this->columns[] = "{$name} TIMESTAMP";
    $this->lastColumnName = $name;
    return $this;
  }

  /**
   * @method timestampTz
   * Adds a timestamp with timezone column.
   *
   * Adds a column to the table that allows storing timestamp with timezone.
   *
   * @param string $name The name of the column.
   * @return self
   */
  public function timestampTz(string $name): self
  {
    $this->columns[] = "{$name} TIMESTAMP WITH TIME ZONE";
    $this->lastColumnName = $name;
    return $this;
  }

  /**
   * @method timestamps
   * Adds created_at and updated_at columns.
   *
   * Adds two columns to the table that allows storing created_at and updated_at timestamps.
   *
   * @return self
   */
  public function timestamps(): self
  {
    if ($this->isSQLite()) {
      $this->columns[] = "created_at TEXT DEFAULT CURRENT_TIMESTAMP";
      $this->columns[] = "updated_at TEXT DEFAULT CURRENT_TIMESTAMP";
    } else {
      $this->columns[] = "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
      $this->columns[] = "updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
    }
    return $this;
  }

  /**
   * @method tinyIncrements
   * Adds a tinyint column with auto-increment and primary key constraints.
   *
   * Adds a column to the table that allows storing tinyint numbers.
   * Sets the column as the primary key with auto-increment.
   *
   * @param string $name The name of the column.
   * @return self
   */
  public function tinyIncrements(string $name): self
  {
    $this->columns[] = "{$name} TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY";
    $this->lastColumnName = $name;
    return $this;
  }

  /**
   * @method tinyInteger
   * Adds a tinyint column.
   *
   * Adds a column to the table that allows storing tinyint numbers.
   *
   * @param string $name The name of the column.
   * @return self
   */
  public function tinyInteger(string $name): self
  {
    $this->columns[] = "{$name} TINYINT";
    $this->lastColumnName = $name;
    return $this;
  }

  /**
   * @method unsignedBigInteger
   * Adds an unsigned big integer column.
   *
   * Adds a column to the table that allows storing unsigned big integer numbers.
   *
   * @param string $name The name of the column.
   * @return self
   */
  public function unsignedBigInteger(string $name): self
  {
    $this->columns[] = "`{$name}` BIGINT UNSIGNED";
    $this->lastColumnName = $name;
    return $this;
  }


  /**
   * @method unsignedDecimal
   * Adds an unsigned decimal column.
   *
   * Adds a column to the table that allows storing unsigned decimal numbers.
   *
   * @param string $name The name of the column.
   * @param int $precision The precision of the column.
   * @param int $scale The scale of the column.
   * @return self
   */
  public function unsignedDecimal(string $name, int $precision = 10, int $scale = 2): self
  {
    $this->columns[] = "{$name} DECIMAL({$precision}, {$scale}) UNSIGNED";
    $this->lastColumnName = $name;
    return $this;
  }

  /**
   * @method unsignedInteger
   * Adds an unsigned integer column.
   *
   * Adds a column to the table that allows storing unsigned integer numbers.
   *
   * @param string $name The name of the column.
   * @return self
   */
  public function unsignedInteger(string $name): self
  {
    $this->columns[] = "`{$name}` INT UNSIGNED";
    $this->lastColumnName = $name;
    return $this;
  }

  /**
   * @method unsignedMediumInteger
   * Adds an unsigned medium integer column.
   *
   * Adds a column to the table that allows storing unsigned medium integer numbers.
   *
   * @param string $name The name of the column.
   * @return self
   */
  public function unsignedMediumInteger(string $name): self
  {
    $this->columns[] = "`{$name}` MEDIUMINT UNSIGNED";
    $this->lastColumnName = $name;
    return $this;
  }

  /**
   * @method unsignedSmallInteger
   * Adds an unsigned small integer column.
   *
   * Adds a column to the table that allows storing unsigned small integer numbers.
   *
   * @param string $name The name of the column.
   * @return self
   */
  public function unsignedSmallInteger(string $name): self
  {
    $this->columns[] = "`{$name}` SMALLINT UNSIGNED";
    $this->lastColumnName = $name;
    return $this;
  }

  /**
   * @method unsignedTinyInteger
   * Adds an unsigned tiny integer column.
   *
   * Adds a column to the table that allows storing unsigned tiny integer numbers.
   *
   * @param string $name The name of the column.
   * @return self
   */
  public function unsignedTinyInteger(string $name): self
  {
    $this->columns[] = "`{$name}` TINYINT UNSIGNED";
    $this->lastColumnName = $name;
    return $this;
  }

  public function uuid(string $name): self
  {
    $this->columns[] = "`{$name}` CHAR(36)";
    $this->lastColumnName = $name;
    return $this;
  }


  /**
   * @method trashable
   * Adds a soft delete column.
   *
   * Adds a column to the table that allows soft deleting records.
   *
   * @param string $name The name of the column.
   * @return self
   */
  public function trashable(string $name = 'trashed_at'): self
  {
    $this->columns[] = "{$name} TIMESTAMP NULL DEFAULT NULL";
    $this->lastColumnName = $name;
    return $this;
  }

  public function unique(?string $indexName = null): self
  {
    $column = $this->lastColumnName;

    if ($this->driver === 'sqlite') {
      // For SQLite, add UNIQUE directly to column definition
      $this->columns[array_key_last($this->columns)] .= ' UNIQUE';
    } else {
      // For other databases, use standard unique constraint
      $indexName = $indexName ?: "{$this->table}_{$column}_unique";
      $this->indexes[] = "CONSTRAINT `{$indexName}` UNIQUE (`{$column}`)";
    }

    return $this;
  }

  public function index(?string $indexName = null): self
  {
    $column = $this->lastColumnName;

    if ($this->driver === 'sqlite') {
      $indexName = $indexName ?: "{$this->table}_{$column}_idx";
      $this->postCommands[] = "CREATE INDEX `{$indexName}` ON `{$this->table}` (`{$column}`)";
    } else {
      $indexName = $indexName ?: "{$column}_index";
      $this->indexes[] = "INDEX `{$indexName}` (`{$column}`)";
    }

    return $this;
  }

  public function primary(): self
  {
    $column = $this->lastColumnName;
    $this->indexes[] = "PRIMARY KEY (`{$column}`)";
    return $this;
  }

  public function foreignId(string $column): self
  {
    if ($this->isSQLite()) {
      $this->columns[] = "{$this->quoteIdentifier($column)} INTEGER";
    } else {
      $this->unsignedBigInteger($column);
    }
    $this->lastFkConstraint = $column;
    return $this;
  }

  public function foreignIdFor(string $modelClass, ?string $column = null): self
  {
    $table = StringUtils::plural(StringUtils::snakeCase(StringUtils::classBasename($modelClass)));
    $column = $column ?: StringUtils::singular($table) . '_id';
    return $this->foreignId($column);
  }

  public function constrained(string $references = 'id', ?string $onTable = null): self
  {
    if (!$this->lastFkConstraint) {
      throw new \RuntimeException('No foreign key column to constrain.');
    }

    $onTable = $onTable ?? StringUtils::plural(StringUtils::beforeLast($this->lastFkConstraint, '_id'));

    $fkSql = sprintf(
      "FOREIGN KEY (%s) REFERENCES %s (%s)",
      $this->quoteIdentifier($this->lastFkConstraint),
      $this->quoteIdentifier($onTable),
      $this->quoteIdentifier($references)
    );

    if (!$this->isSQLite()) {
      $constraintName = "fk_{$this->table}_{$this->lastFkConstraint}";
      $fkSql = "CONSTRAINT `{$constraintName}` $fkSql";
    }

    $this->foreignKeys[] = $fkSql;
    $this->lastFkConstraint = null;
    return $this;
  }


  public function onUpdate(string $action): self
  {
    $key = array_key_last($this->foreignKeys);
    $this->foreignKeys[$key] .= " ON UPDATE {$action}";
    return $this;
  }

  public function onDelete(string $action): self
  {
    $key = array_key_last($this->foreignKeys);
    $this->foreignKeys[$key] .= " ON DELETE {$action}";
    return $this;
  }

  // Column Modifiers
  public function nullable(): self
  {
    $this->modifyLastColumn(fn($def) => rtrim($def) . ' NULL');
    return $this;
  }

  public function after(string $column): self
  {
    $this->modifyLastColumn(fn($def) => $def . " AFTER `{$column}`");
    return $this;
  }

  public function autoIncrement(): self
  {
    $this->modifyLastColumn(fn($def) => $def . ' AUTO_INCREMENT');
    return $this;
  }

  public function comment(string $text): self
  {
    $this->modifyLastColumn(fn($def) => $def . " COMMENT '{$text}'");
    return $this;
  }

  private function modifyLastColumn(callable $modifier): void
  {
    $key = array_key_last($this->columns);
    if (null === $key) {
      throw new \RuntimeException('No columns defined');
    }
    $this->columns[$key] = $modifier($this->columns[$key]);
  }


  public function engine(string $engine): self
  {
    $this->engine = $engine;
    return $this;
  }

  public function charset(string $charset): self
  {
    $this->charset = $charset;
    return $this;
  }

  public function collation(string $collation): self
  {
    $this->collation = $collation;
    return $this;
  }

  private function compileTableOptions(): string
  {
    if ($this->isSQLite()) {
      return '';
    }

    return sprintf(
      "ENGINE=%s DEFAULT CHARSET=%s COLLATE=%s",
      $this->engine,
      $this->charset,
      $this->collation
    );
  }

  /**
   * @method compileCreate
   * Compiles the create statement.
   *
   * Compiles the create statement from the columns and indexes defined.
   *
   * @return string
   */
  public function compileCreate(): string
  {
    $definitions = array_merge(
      $this->columns,
      $this->indexes,
      $this->foreignKeys
    );

    if ($this->isSQLite()) {
      // SQLite doesn't support CONSTRAINT clauses in CREATE TABLE
      $definitions = array_filter($definitions, function ($item) {
        return !str_contains($item, 'CONSTRAINT');
      });
    }


    return sprintf(
      "CREATE TABLE IF NOT EXISTS `%s` (\n  %s\n) %s;",
      $this->table,
      implode(",\n  ", $definitions),
      $this->compileTableOptions()
    );
  }

  /**
   * @method compileAlter
   * Compiles the alter statement.
   *
   * Compiles the alter statement from the changes defined.
   *
   * @return string
   */
  public function compileAlter(): string
  {
    $changes = array_merge(
      $this->columns,
      $this->indexes,
      $this->drops
    );
    return "ALTER TABLE {$this->table} " . implode(', ', $changes);
  }
}
