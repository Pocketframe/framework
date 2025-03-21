<?php

namespace Pocketframe\PocketORM\Database;

use PDO;
use Pocketframe\Exceptions\Database\QueryException;
use Pocketframe\PocketORM\Concerns\DeepFetch;
use Pocketframe\PocketORM\Essentials\RecordSet;

/**
 * QueryEngine class is responsible for generating SQL queries and
 * executing them.
 *
 * Example
 * $query = new QueryEngine('users');
 * $results = $query
 *   ->select(['name', 'email'])
 *   ->includeTotal('posts', 'post_count')
 *   ->havingLinked('posts', function($query) {
 *     $query->where('views', '>', 100);
 *   })
 *   ->get();
 *
 * @package Pocketframe\PocketORM
 * @author William Asaba
 */

class QueryEngine
{
  use DeepFetch;
  private string $table;
  private array $select = ['*'];
  private array $wheres = [];
  private array $joins = [];
  private array $groups = [];
  private array $havings = [];
  private array $orders = [];
  private ?int $limit = null;
  private array $bindings = [];

  public function __construct(string $table)
  {
    $this->table = $table;
  }

  public function select(array $columns): self
  {
    $this->select = $columns;
    return $this;
  }

  public function where(string $column, string $operator, $value): self
  {
    $this->wheres[] = "{$column} {$operator} ?";
    $this->bindings[] = $value;
    return $this;
  }

  public function join(string $table, string $first, string $operator, string $second): self
  {
    $this->joins[] = "JOIN {$table} ON {$first} {$operator} {$second}";
    return $this;
  }

  public function groupBy(string $column): self
  {
    $this->groups[] = $column;
    return $this;
  }

  public function having(string $column, string $operator, $value): self
  {
    $this->havings[] = "{$column} {$operator} ?";
    $this->bindings[] = $value;
    return $this;
  }

  public function orderBy(string $column, string $direction = 'ASC'): self
  {
    $this->orders[] = "{$column} {$direction}";
    return $this;
  }

  public function limit(int $limit): self
  {
    $this->limit = $limit;
    return $this;
  }

  public function get(): RecordSet
  {
    $sql = $this->compileSelect();
    return $this->execute($sql);
  }

  public function insertBatch(array $data): int
  {
    if (empty($data)) return 0;

    $columns = implode(', ', array_keys($data[0]));
    $placeholders = implode(', ', array_fill(0, count($data[0]), '?'));
    $values = [];

    foreach ($data as $row) {
      $values = array_merge($values, array_values($row));
    }

    $sql = "INSERT INTO {$this->table} ({$columns}) VALUES " .
      implode(', ', array_fill(0, count($data), "({$placeholders})"));

    try {
      $stmt = Connection::getInstance()->prepare($sql);
      $stmt->execute($values);
      return $stmt->rowCount();
    } catch (\PDOException $e) {
      throw new QueryException("Batch insert failed: " . $e->getMessage());
    }
  }

  private function compileSelect(): string
  {
    $sql = "SELECT " . implode(', ', $this->select) . " FROM {$this->table}";

    if (!empty($this->joins)) {
      $sql .= " " . implode(" ", $this->joins);
    }

    if (!empty($this->wheres)) {
      $sql .= " WHERE " . implode(" AND ", $this->wheres);
    }

    if (!empty($this->groups)) {
      $sql .= " GROUP BY " . implode(", ", $this->groups);
    }

    if (!empty($this->havings)) {
      $sql .= " HAVING " . implode(" AND ", $this->havings);
    }

    if (!empty($this->orders)) {
      $sql .= " ORDER BY " . implode(", ", $this->orders);
    }

    if ($this->limit !== null) {
      $sql .= " LIMIT " . $this->limit;
    }

    return $sql;
  }

  public function havingLinked(string $relation, callable $constraints): self
  {
    $related = new $relation();
    $table = $related::getTable();

    $this->join(
      $table,
      "{$this->table}.id",
      '=',
      "{$table}." . $related->guessForeignKey()
    );

    $constraints(new QueryEngine($table));

    return $this;
  }

  public function includeTotal(string $relation, string $as): self
  {
    $related = new $relation();
    $foreignKey = $related->guessForeignKey();

    $this->selectSub(
      (new QueryEngine($related::getTable()))
        ->selectRaw("COUNT(*)")
        ->whereColumn(
          "{$related::getTable()}.{$foreignKey}",
          "=",
          "{$this->table}.id"
        ),
      $as
    );

    return $this;
  }

  public function selectRaw(string $expression): self
  {
    $this->select[] = $expression;
    return $this;
  }

  public function whereColumn(string $first, string $operator, string $second): self
  {
    $this->wheres[] = "{$first} {$operator} {$second}";
    return $this;
  }


  private function selectSub(QueryEngine $query, string $alias): void
  {
    $this->select[] = "({$query->compileSelect()}) AS {$alias}";
    $this->bindings = array_merge($this->bindings, $query->bindings);
  }

  public function first(): ?object
  {
    $this->limit(1);
    return $this->get()->first();
  }

  public function insert(array $data): int
  {
    $columns = implode(', ', array_keys($data));
    $placeholders = implode(', ', array_fill(0, count($data), '?'));

    $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
    $this->execute($sql, array_values($data));

    return Connection::getInstance()->lastInsertId();
  }

  public function update(array $data): int
  {
    $set = implode(', ', array_map(fn($k) => "{$k} = ?", array_keys($data)));
    $values = array_values($data);

    $sql = "UPDATE {$this->table} SET {$set}";

    if (!empty($this->wheres)) {
      $sql .= " WHERE " . implode(' AND ', $this->wheres);
      $values = array_merge($values, $this->bindings);
    }

    $stmt = Connection::getInstance()->prepare($sql);
    $stmt->execute($values);

    return $stmt->rowCount();
  }

  public function delete(): int
  {
    $sql = "DELETE FROM {$this->table}";

    if (!empty($this->wheres)) {
      $sql .= " WHERE " . implode(' AND ', $this->wheres);
    }

    $stmt = Connection::getInstance()->prepare($sql);
    $stmt->execute($this->bindings);

    return $stmt->rowCount();
  }

  private function execute(string $sql, array $params = []): RecordSet
  {
    try {
      $stmt = Connection::getInstance()->prepare($sql);
      $stmt->execute($params);
      return new RecordSet($stmt->fetchAll(PDO::FETCH_OBJ));
    } catch (\PDOException $e) {
      throw new QueryException("Query failed: " . $e->getMessage());
    }
  }
}
