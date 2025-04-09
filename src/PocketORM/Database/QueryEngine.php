<?php

namespace Pocketframe\PocketORM\Database;

use PDO;
use PDOException;
use Pocketframe\PocketORM\Concerns\DeepFetch;
use Pocketframe\PocketORM\Entity\Entity;
use Pocketframe\PocketORM\Essentials\DataSet;
use Pocketframe\PocketORM\Schema\Schema;

class QueryEngine
{
  use DeepFetch;

  protected string $table = '';
  protected array $select = ['*'];
  protected array $wheres = [];
  protected array $joins = [];
  protected array $groups = [];
  protected array $havings = [];
  protected array $orders = [];
  protected ?int $limit = null;
  protected array $bindings = [];
  protected array $insertData = [];
  protected array $updateData = [];
  protected bool $isDelete = false;
  protected array $rawSelects = [];
  protected ?string $keyByColumn = null;
  private ?string $entityClass;
  protected bool $withTrashed = false;
  protected bool $onlyTrashed = false;

  public function __construct($entity)
  {
    if (is_object($entity)) {
      $entityClass = get_class($entity);
      if (!is_subclass_of($entityClass, Entity::class)) {
        throw new \InvalidArgumentException(sprintf(
          "Invalid entity instance. Class '%s' must extend %s.",
          $entityClass,
          Entity::class
        ));
      }
      $this->entityClass = $entityClass;
      $this->table = $entityClass::getTable();
    } elseif (is_string($entity)) {
      if (class_exists($entity) && is_subclass_of($entity, Entity::class) && method_exists($entity, 'getTable')) {
        $this->entityClass = $entity;
        $this->table = $entity::getTable();
      } else {
        // Treat as table name
        $this->table = $entity;
        $this->entityClass = null;
      }
    } else {
      throw new \InvalidArgumentException(sprintf(
        "Invalid entity or table name. Must be an Entity class/instance or a table name string. Given: %s",
        gettype($entity)
      ));
    }
  }

  /**
   * Static factory method for building a new QueryEngine instance.
   *
   * @param mixed $entity An Entity class name, instance, or table name string.
   * @return self
   */
  public static function for($entity): self
  {
    return new self($entity);
  }
  // SELECT METHODS

  public function select(array $columns): self
  {
    $this->select = $columns;
    return $this;
  }

  /**
   * Add raw SQL expression to SELECT clause
   *
   * @param string $expression Raw SQL select expression
   * @return self
   *
   * @example ->selectRaw('COUNT(*) AS total')
   * @example ->selectRaw('MAX(created_at) AS last_date')
   */
  public function selectRaw(string $expression): self
  {
    $this->select = [];
    $this->rawSelects[] = $expression;
    return $this;
  }


  /**
   * Key results by specified column
   *
   * @param string $column Column name to use as array keys
   * @return self
   *
   * @note If multiple rows have the same column value,
   *       the last one will overwrite previous entries
   * @example ->keyBy('id')->get() returns results indexed by ID
   */
  public function keyBy(string $column): self
  {
    $this->keyByColumn = $column;
    return $this;
  }


  // WHERE METHODS

  public function where(string $column, string $operator, $value, string $boolean = 'AND'): self
  {
    $this->wheres[] = [
      'type' => 'basic',
      'column' => $column,
      'operator' => $operator,
      'value' => $value,
      'boolean' => $boolean,
    ];
    $this->bindings[] = $value;
    return $this;
  }

  public function orWhere(string $column, string $operator, $value): self
  {
    return $this->where($column, $operator, $value, 'OR');
  }

  public function whereIn(string $column, array $values, string $boolean = 'AND', bool $not = false): self
  {
    if (empty($values)) {
      return $this;
    }

    $this->wheres[] = [
      'type' => 'in',
      'column' => $column,
      'values' => $values,
      'boolean' => $boolean,
      'not' => $not,
    ];
    $this->bindings = array_merge($this->bindings, $values);
    return $this;
  }


  public function orWhereIn(string $column, array $values): self
  {
    return $this->whereIn($column, $values, 'OR');
  }

  public function whereNull(string $column, string $boolean = 'AND', bool $not = false): self
  {
    $this->wheres[] = [
      'type' => 'null',
      'column' => $column,
      'boolean' => $boolean,
      'not' => $not,
    ];
    return $this;
  }

  public function orWhereNull(string $column): self
  {
    return $this->whereNull($column, 'OR');
  }

  public function whereNotNull(string $column, string $boolean = 'AND'): self
  {
    return $this->whereNull($column, $boolean, true);
  }

  public function whereColumn(string $first, string $operator, string $second): self
  {
    $this->wheres[] = "{$first} {$operator} {$second}";
    return $this;
  }

  public function orWhereColumn(string $first, string $operator, string $second): self
  {
    return $this->whereColumn($first, $operator, $second, 'OR');
  }

  // JOIN METHODS

  public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
  {
    $this->joins[] = "$type JOIN $table ON $first $operator $second";
    return $this;
  }

  // GROUPING METHODS

  public function groupBy(string $column): self
  {
    $this->groups[] = $column;
    return $this;
  }

  public function having(string $column, string $operator, $value): self
  {
    $this->havings[] = "$column $operator ?";
    $this->bindings[] = $value;
    return $this;
  }

  // ORDER & LIMIT

  public function orderBy(string $column, string $direction = 'ASC'): self
  {
    $this->orders[] = "$column $direction";
    return $this;
  }

  public function limit(int $limit): self
  {
    $this->limit = $limit;
    return $this;
  }

  // CRUD OPERATIONS

  public function insert(array $data): int
  {
    $columns = implode(', ', array_map(fn($col) => "`$col`", array_keys($data)));
    $placeholders = implode(', ', array_fill(0, count($data), '?'));

    $sql = "INSERT INTO `{$this->table}` ($columns) VALUES ($placeholders)";
    $this->executeStatement($sql, array_values($data));

    // Get last insert ID from connection, not statement
    return (int) Connection::getInstance()->lastInsertId();
  }

  public function insertBatch(array $rows): int
  {
    if (empty($rows)) return 0;

    $columns = implode(', ', array_map(fn($col) => "`$col`", array_keys($rows[0])));
    $placeholders = implode(', ', array_fill(0, count($rows[0]), '?'));
    $values = [];

    foreach ($rows as $row) {
      $values = array_merge($values, array_values($row));
    }

    $sql = "INSERT INTO `{$this->table}` ($columns) VALUES " .
      rtrim(str_repeat("($placeholders), ", count($rows)), ', ');

    $stmt = $this->executeStatement($sql, $values);
    return $stmt->rowCount();
  }

  public function update(array $data): int
  {
    $set = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($data)));
    $sql = "UPDATE `{$this->table}` SET $set " . $this->compileWheres();
    $bindings = array_merge(array_values($data), $this->bindings);
    $stmt = $this->executeStatement($sql, $bindings);
    return $stmt->rowCount();
  }

  public function delete(): int
  {
    $sql = "DELETE FROM `{$this->table}` " . $this->compileWheres();
    $stmt = $this->executeStatement($sql, $this->bindings);
    return $stmt->rowCount();
  }

  // QUERY EXECUTION

  public function get(): DataSet
  {
    $this->applySoftDeleteConditions();
    $sql = $this->compileSelect();
    $records = $this->executeQuery($sql);

    // Perform eager loading using DeepFetch’s methods.
    foreach ($this->includes as $relation) {
      $this->loadRelation($records, $relation);
    }

    return $records;
  }

  /**
   * Get the first record of the query result
   *
   * @return object|null The first entity or null
   */
  public function first(): ?object
  {
    $this->applySoftDeleteConditions();
    $this->limit(1);
    $result = $this->get();
    return $result->first();
  }

  /**
   * Find a single record by primary key (ID)
   *
   * @param int|string $id The primary key value
   * @return object|null The found entity or null
   */
  public function find($id): ?object
  {
    $this->applySoftDeleteConditions();
    return $this->where('id', '=', $id)->first();
  }


  /**
   * Find a record by primary key or throw exception
   *
   * @param int|string $id The primary key value
   * @return object The found entity
   * @throws \RuntimeException If no record found
   */
  public function findOrFail($id): object
  {
    if ($result = $this->find($id)) {
      return $result;
    }

    throw new \RuntimeException("No record found for ID {$id}");
  }

  public function withTrashed(): self
  {
    $this->withTrashed = true;
    return $this;
  }

  public function onlyTrashed(): self
  {
    $this->onlyTrashed = true;
    return $this;
  }

  protected function applySoftDeleteConditions(): void
  {
    if ($this->entityClass === null) {
      return;
    }

    $trashColumn = $this->getEntityTrashColumn();

    if (!Schema::tableHasColumn($this->table, $trashColumn)) return;

    if ($this->onlyTrashed) {
      $this->whereNotNull($trashColumn);
    } elseif (!$this->withTrashed) {
      $this->whereNull($trashColumn);
    }
  }

  private function getEntityTrashColumn(): string
  {
    $default = 'trashed_at';

    if (!$this->entityClass || !class_exists($this->entityClass)) {
      return $default;
    }

    try {
      $reflection = new \ReflectionClass($this->entityClass);
      $property = $reflection->getProperty('trashColumn');
      $property->setAccessible(true); // Bypass visibility
      return $property->getValue() ?? $default;
    } catch (\ReflectionException $e) {
      return $default;
    }
  }

  // SQL COMPILATION

  protected function compileSelect(): string
  {
    $selectColumns = [];

    // Handle regular selects
    foreach ($this->select as $col) {
      $selectColumns[] = $col === '*' ? $col : "`$col`";
    }

    // Handle raw selects
    foreach ($this->rawSelects as $raw) {
      $selectColumns[] = $raw;
    }

    if (empty($selectColumns)) {
      $selectColumns = ['*'];
    }

    $sql = "SELECT " . implode(', ', $selectColumns) . " FROM `{$this->table}`";

    if (!empty($this->joins)) {
      $sql .= ' ' . implode(' ', $this->joins);
    }

    $sql .= $this->compileWheres();

    if (!empty($this->groups)) {
      $sql .= ' GROUP BY ' . implode(', ', $this->groups);
    }

    if (!empty($this->havings)) {
      $sql .= ' HAVING ' . implode(' AND ', $this->havings);
    }

    if (!empty($this->orders)) {
      $sql .= ' ORDER BY ' . implode(', ', $this->orders);
    }

    if ($this->limit !== null) {
      $sql .= " LIMIT {$this->limit}";
    }

    return $sql;
  }

  protected function compileWheres(): string
  {
    if (empty($this->wheres)) return '';

    $clauses = [];
    foreach ($this->wheres as $index => $where) {
      $clause = $index === 0 ? '' : $where['boolean'] . ' ';

      // Handle proper quoting for table.column syntax.
      $column = $where['column'];
      if (strpos($column, '.') !== false) {
        $parts = explode('.', $column);
        $column = implode('.', array_map(fn($part) => "`$part`", $parts));
      } else {
        $column = "`$column`";
      }

      switch ($where['type']) {
        case 'basic':
          $clause .= "$column {$where['operator']} ?";
          break;
        case 'in':
          $placeholders = implode(', ', array_fill(0, count($where['values']), '?'));
          $not = $where['not'] ? 'NOT ' : '';
          $clause .= "$column {$not}IN ($placeholders)";
          break;
        case 'null':
          $not = $where['not'] ? 'NOT ' : '';
          $clause .= "$column IS {$not}NULL";
          break;
      }

      $clauses[] = $clause;
    }
    return ' WHERE ' . implode(' ', $clauses);
  }

  // HELPER METHODS

  protected function executeQuery(string $sql): DataSet
  {
    $stmt = $this->executeStatement($sql, $this->bindings);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($this->entityClass && class_exists($this->entityClass)) {
      $hydrated = [];
      foreach ($data as $record) {
        $entity = new $this->entityClass;
        $integerColumns = $entity->getIntegerColumns();

        foreach ($record as $key => $value) {
          // Cast IDs to integers
          if (in_array($key, $integerColumns, true)) {
            $value = (int)$value;
          }
          $entity->attributes[$key] = $value;
        }
        $hydrated[] = $entity;
      }
      $data = $hydrated;
    }

    return new DataSet($data);
  }

  protected function executeStatement(string $sql, array $bindings = [])
  {
    try {
      $stmt = Connection::getInstance()->prepare($sql);
      $stmt->execute($bindings);
      return $stmt;
    } catch (PDOException $e) {
      throw new PDOException($e->getMessage(), (int)$e->getCode(), $e);
    }
  }

  // UTILITY METHODS

  public function toSql(): string
  {
    if (!empty($this->insertData)) {
      return $this->compileInsert();
    } elseif (!empty($this->updateData)) {
      return $this->compileUpdate();
    } elseif ($this->isDelete) {
      return $this->compileDelete();
    } else {
      return $this->compileSelect();
    }
  }

  protected function compileInsert(): string
  {
    $columns = implode(', ', array_map(fn($col) => "`$col`", array_keys($this->insertData)));
    $placeholders = implode(', ', array_fill(0, count($this->insertData), '?'));
    return "INSERT INTO `{$this->table}` ($columns) VALUES ($placeholders)";
  }

  protected function compileUpdate(): string
  {
    $set = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($this->updateData)));
    return "UPDATE `{$this->table}` SET $set " . $this->compileWheres();
  }

  protected function compileDelete(): string
  {
    return "DELETE FROM `{$this->table}` " . $this->compileWheres();
  }

  public function getBindings(): array
  {
    return $this->bindings;
  }
}
