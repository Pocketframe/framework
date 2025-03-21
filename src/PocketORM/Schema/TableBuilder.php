<?php

namespace Pocketframe\PocketORM\Schema;

class TableBuilder
{
  private string $table;
  private array $columns = [];
  private array $indexes = [];
  private array $drops = [];

  public function __construct(string $table)
  {
    $this->table = $table;
  }

  public function increments(string $name): self
  {
    $this->columns[] = "{$name} INT AUTO_INCREMENT PRIMARY KEY";
    return $this;
  }

  public function string(string $name, int $length = 255): self
  {
    $this->columns[] = "{$name} VARCHAR({$length})";
    return $this;
  }

  public function timestamp(string $name): self
  {
    $this->columns[] = "{$name} TIMESTAMP";
    return $this;
  }

  public function index(array|string $columns): self
  {
    $cols = implode(', ', (array)$columns);
    $this->indexes[] = "INDEX idx_{$cols} ({$cols})";
    return $this;
  }

  public function dropColumn(string $column): self
  {
    $this->drops[] = "DROP COLUMN {$column}";
    return $this;
  }

  public function compileCreate(): string
  {
    $columns = implode(', ', $this->columns);
    $indexes = implode(', ', $this->indexes);
    return "CREATE TABLE {$this->table} ({$columns}{$indexes})";
  }

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
