<?php

namespace Pocketframe\PocketORM\Database;

class QueryBuilder
{
  protected string $table;
  protected array $columns = ['*'];
  protected array $wheres  = [];
  protected array $bindings = [];
  protected array $joins   = [];
  protected ?string $orderBy = null;
  protected ?string $limit   = null;
  protected bool $isDelete = false;
  protected array $insertData = [];
  protected array $updateData = [];

  public function __construct(string $table)
  {
    $this->table = $table;
  }

  // SELECT
  public function select(array $columns): self
  {
    $this->columns = $columns;
    return $this;
  }

  // WHERE
  public function where(string $column, string $operator, mixed $value): self
  {
    $this->wheres[] = "$column $operator ?";
    $this->bindings[] = $value;
    return $this;
  }

  public function whereIn(string $column, array $values): self
  {
    $placeholders = implode(',', array_fill(0, count($values), '?'));
    $this->wheres[] = "$column IN ($placeholders)";
    $this->bindings = array_merge($this->bindings, $values);
    return $this;
  }

  public function whereNull(string $column): self
  {
    $this->wheres[] = "$column IS NULL";
    return $this;
  }

  public function whereNotNull(string $column): self
  {
    $this->wheres[] = "$column IS NOT NULL";
    return $this;
  }

  // ORDER BY, LIMIT
  public function orderBy(string $column, string $direction = 'ASC'): self
  {
    $this->orderBy = "ORDER BY $column $direction";
    return $this;
  }

  public function limit(int $limit): self
  {
    $this->limit = "LIMIT $limit";
    return $this;
  }

  // JOIN
  public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
  {
    $this->joins[] = "$type JOIN $table ON $first $operator $second";
    return $this;
  }

  // INSERT
  public function insert(array $data): self
  {
    $this->insertData = $data;
    return $this;
  }

  // UPDATE
  public function update(array $data): self
  {
    $this->updateData = $data;
    return $this;
  }

  // DELETE
  public function delete(): self
  {
    $this->isDelete = true;
    return $this;
  }

  public function toSql(): string
  {
    // INSERT
    if (!empty($this->insertData)) {
      $cols = implode(', ', array_keys($this->insertData));
      $placeholders = implode(', ', array_fill(0, count($this->insertData), '?'));
      return "INSERT INTO {$this->table} ($cols) VALUES ($placeholders)";
    }

    // UPDATE
    if (!empty($this->updateData)) {
      $setParts = [];
      foreach ($this->updateData as $col => $val) {
        $setParts[] = "$col = ?";
      }
      $setClause = implode(', ', $setParts);
      return "UPDATE {$this->table} SET $setClause " . $this->buildWhere();
    }

    // DELETE
    if ($this->isDelete) {
      return "DELETE FROM {$this->table} " . $this->buildWhere();
    }

    // SELECT
    $cols = implode(', ', $this->columns);
    $sql = "SELECT $cols FROM {$this->table}";
    if (!empty($this->joins)) {
      $sql .= ' ' . implode(' ', $this->joins);
    }
    $sql .= $this->buildWhere();
    if ($this->orderBy) {
      $sql .= " {$this->orderBy}";
    }
    if ($this->limit) {
      $sql .= " {$this->limit}";
    }
    return $sql;
  }

  public function getBindings(): array
  {
    if (!empty($this->insertData)) {
      return array_values($this->insertData);
    }
    if (!empty($this->updateData)) {
      return array_values($this->updateData);
    }
    return $this->bindings;
  }

  private function buildWhere(): string
  {
    if (empty($this->wheres)) {
      return '';
    }
    return ' WHERE ' . implode(' AND ', $this->wheres);
  }
}
