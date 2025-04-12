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

  protected string $table        = '';
  protected array $select        = ['*'];
  protected array $wheres        = [];
  protected array $joins         = [];
  protected array $groups        = [];
  protected array $havings       = [];
  protected array $orders        = [];
  protected ?int $limit          = null;
  protected array $bindings      = [];
  protected array $insertData    = [];
  protected array $updateData    = [];
  protected bool $isDelete       = false;
  protected array $rawSelects    = [];
  protected ?string $keyByColumn = null;
  protected bool $withTrashed    = false;
  protected bool $onlyTrashed    = false;
  private ?string $entityClass;

  /**
   * Constructor
   *
   * This class is used to generate database queries for the PocketORM's entities. The * query engine is used to fetch, insert, update and delete records from the database. The query engine is a part of the PocketORM's fluent interface, and it is used to chain methods to build complex queries.
   * The query engine is also used to inject the entity class to the query builder, so the query builder knows which table to query.
   *
   * @param mixed $entity An Entity class name, instance, or table name string.
   */
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

  /**
   * Add a basic WHERE condition
   *
   * Add a basic WHERE condition to the query.
   *
   * @param string $column Column name
   * @param string $operator Comparison operator
   * @param mixed $value Value to compare
   * @param string $boolean Logical operator ('AND' or 'OR')
   * @return self
   *
   * @example ->where('id', '=', 1)
   * @example ->where('name', 'like', '%John%')
   */
  public function where(string $column, ?string $operator = null, $value = null, string $boolean = 'AND'): self
  {
    if (func_num_args() === 2) {
      $value = $operator;
      $operator = '=';
    } elseif ($value === null) {
      $value = $operator;
      $operator = '=';
    }

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

  /**
   * Add an OR WHERE condition
   *
   * Add an OR WHERE condition to the query.
   *
   * @param string $column Column name
   * @param string $operator Comparison operator
   * @param mixed $value Value to compare
   * @return self
   *
   * @example ->orWhere('name', 'like', '%John%')
   */
  public function orWhere(string $column, ?string $operator = null, $value = null): self
  {
    return $this->where($column, $operator, $value, 'OR');
  }

  /**
   * Add a WHERE IN condition
   *
   * Add a WHERE IN condition to the query.
   *
   * @param string $column Column name
   * @param array $values Array of values to compare
   * @param string $boolean Logical operator ('AND' or 'OR')
   * @param bool $not Whether to use NOT IN
   * @return self
   *
   * @example ->whereIn('id', [1, 2, 3])
   * @example ->whereIn('name', ['John', 'Jane'], 'OR')
   */
  public function whereIn(string $column, array $values, string $boolean = 'AND', bool $not = false): self
  {
    if (empty($values)) {
      return $this;
    }

    // If values array is empty, don't add the where clause.
    // This helps avoid "WHERE IN ()" syntax errors.
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

  /**
   * Add an OR WHERE IN condition
   *
   * Add an OR WHERE IN condition to the query.
   *
   * @param string $column Column name
   * @param array $values Array of values to compare
   * @return self
   *
   * @example ->orWhereIn('id', [1, 2, 3])
   */
  public function orWhereIn(string $column, array $values): self
  {
    return $this->whereIn($column, $values, 'OR');
  }

  /**
   * Add a WHERE NULL condition
   *
   * Add a WHERE NULL condition to the query.
   *
   * @param string $column Column name
   * @param string $boolean Logical operator ('AND' or 'OR')
   * @param bool $not Whether to use NOT NULL
   * @return self
   *
   * @example ->whereNull('deleted_at')
   * @example ->whereNull('name', 'OR')
   */
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

  /**
   * Add an OR WHERE NULL condition
   *
   * Add an OR WHERE NULL condition to the query.
   *
   * @param string $column Column name
   * @return self
   *
   * @example ->orWhereNull('deleted_at')
   */
  public function orWhereNull(string $column): self
  {
    return $this->whereNull($column, 'OR');
  }

  /**
   * Add a WHERE NOT NULL condition
   *
   * Add a WHERE NOT NULL condition to the query.
   *
   * @param string $column Column name
   * @param string $boolean Logical operator ('AND' or 'OR')
   * @return self
   *
   * @example ->whereNotNull('deleted_at')
   * @example ->whereNotNull('name', 'OR')
   */
  public function whereNotNull(string $column, string $boolean = 'AND'): self
  {
    return $this->whereNull($column, $boolean, true);
  }

  /**
   * Add a WHERE column condition
   *
   * Add a WHERE condition comparing two columns to the query.
   *
   * @param string $first First column name
   * @param string $operator Comparison operator
   * @param string $second Second column name
   * @return self
   *
   * @example ->whereColumn('id', '=', 'id')
   */
  public function whereColumn(string $first, $operatorOrSecond, $secondOrNone = null, string $boolean = 'AND'): self
  {
    $operator = '=';
    $second = $operatorOrSecond;

    if ($secondOrNone !== null) {
      $operator = $operatorOrSecond;
      $second = $secondOrNone;
    }

    $this->wheres[] = [
      'type' => 'column',
      'first' => $first,
      'operator' => $operator,
      'second' => $second,
    ];
    return $this;
  }

  /**
   * Add an OR WHERE column condition
   *
   * Add an OR WHERE column condition to the query.
   *
   * @param string $first First column name
   * @param string $operator Comparison operator
   * @param string $second Second column name
   * @return self
   *
   * @example ->orWhereColumn('id', '=', 'id')
   */
  public function orWhereColumn(string $first, $operatorOrSecond, $secondOrNone = null): self
  {
    return $this->whereColumn($first, $operatorOrSecond, $secondOrNone, 'OR');
  }

  // JOIN METHODS

  /**
   * Add a JOIN condition
   *
   * Add a JOIN condition to the query.
   *
   * @param string $table Table name
   * @param string $first First column name
   * @param string $operator Comparison operator
   * @param string $second Second column name
   * @param string $type Type of join ('INNER', 'LEFT', 'RIGHT')
   * @return self
   *
   * @example ->join('users', 'id', '=', 'user_id')
   */
  public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
  {
    $this->joins[] = "$type JOIN $table ON $first $operator $second";
    return $this;
  }

  // GROUPING METHODS

  /**
   * Add a GROUP BY condition
   *
   * Add a GROUP BY condition to the query.
   *
   * @param string $column Column name
   * @return self
   *
   * @example ->groupBy('id')
   */
  public function groupBy(string $column): self
  {
    $this->groups[] = $column;
    return $this;
  }

  /**
   * Add a HAVING condition
   *
   * Add a HAVING condition to the query.
   *
   * @param string $column Column name
   * @param string $operator Comparison operator
   * @param mixed $value Value to compare
   * @return self
   *
   * @example ->having('id', '>', 10)
   */
  public function having(string $column, string $operator, $value): self
  {
    $this->havings[] = "$column $operator ?";
    $this->bindings[] = $value;
    return $this;
  }

  // ORDER & LIMIT

  /**
   * Add an ORDER BY condition
   *
   * Add an ORDER BY condition to the query.
   *
   * @param string $column Column name
   * @param string $direction Sorting direction ('ASC' or 'DESC')
   * @return self
   *
   * @example ->orderBy('id', 'DESC')
   */
  public function orderBy(string $column, string $direction = 'ASC'): self
  {
    $this->orders[] = "$column $direction";
    return $this;
  }

  /**
   * Add an ORDER BY DESC condition
   *
   * Add an ORDER BY DESC condition to the query.
   *
   * @param string $column Column name
   * @return self
   *
   * @example ->byDesc('id')
   */
  public function byDesc(string $column): self
  {
    return $this->orderBy($column, 'DESC');
  }

  /**
   * Add an ORDER BY ASC condition
   *
   * Add an ORDER BY ASC condition to the query.
   *
   * @param string $column Column name
   * @return self
   *
   * @example ->byAsc('id')
   */
  public function byAsc(string $column): self
  {
    return $this->orderBy($column, 'ASC');
  }

  /**
   * Add a LIMIT condition
   *
   * Add a LIMIT condition to the query.
   *
   * @param int $limit Number of rows to limit
   * @return self
   *
   * @example ->limit(10)
   */
  public function limit(int $limit): self
  {
    $this->limit = $limit;
    return $this;
  }

  // CRUD OPERATIONS

  /**
   * Insert a new row into the table
   *
   * Insert a new row into the table with the specified data.
   *
   * @param array $data Data to insert
   * @return int Last insert ID
   *
   * @example ->insert(['name' => 'John', 'email' => 'john@example.com'])
   */
  public function insert(array $data): int
  {
    // Build SQL
    $columns = implode(', ', array_map(fn($col) => "`$col`", array_keys($data)));
    $placeholders = implode(', ', array_fill(0, count($data), '?'));

    // Execute
    $sql = "INSERT INTO `{$this->table}` ($columns) VALUES ($placeholders)";
    $this->executeStatement($sql, array_values($data));

    // Get last insert ID from connection, not statement
    return (int) Connection::getInstance()->lastInsertId();
  }

  /**
   * Insert multiple rows into the table
   *
   * Insert multiple rows into the table with the specified data.
   *
   * @param array $rows Array of rows to insert
   * @return int Number of rows inserted
   *
   * @example ->insertBatch([
   *   ['name' => 'John', 'email' => 'john@example.com'],
   *   ['name' => 'Jane', 'email' => 'jane@example.com']
   * ])
   */
  public function insertBatch(array $rows): int
  {
    if (empty($rows)) return 0;

    // Build SQL
    $columns = implode(', ', array_map(fn($col) => "`$col`", array_keys($rows[0])));
    $placeholders = implode(', ', array_fill(0, count($rows[0]), '?'));
    $values = [];

    // Build values
    foreach ($rows as $row) {
      $values = array_merge($values, array_values($row));
    }

    // Build SQL
    $sql = "INSERT INTO `{$this->table}` ($columns) VALUES " .
      rtrim(str_repeat("($placeholders), ", count($rows)), ', ');

    $stmt = $this->executeStatement($sql, $values);
    return $stmt->rowCount();
  }

  /**
   * Update rows in the table
   *
   * Update rows in the table with the specified data.
   *
   * @param array $data Data to update
   * @return int Number of rows updated
   *
   * @example ->update(['name' => 'John', 'email' => 'john@example.com'])
   */
  public function update(array $data): int
  {
    // Build SQL
    $set = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($data)));
    $sql = "UPDATE `{$this->table}` SET $set " . $this->compileWheres();
    $bindings = array_merge(array_values($data), $this->bindings);
    $stmt = $this->executeStatement($sql, $bindings);
    return $stmt->rowCount();
  }

  /**
   * Delete rows from the table
   *
   * Delete rows from the table that match the specified conditions.
   *
   * @return int Number of rows deleted
   *
   * @example ->delete()
   */
  public function delete(): int
  {
    $sql = "DELETE FROM `{$this->table}` " . $this->compileWheres();
    $stmt = $this->executeStatement($sql, $this->bindings);
    return $stmt->rowCount();
  }

  // QUERY EXECUTION

  /**
   * Get all records of the query result
   *
   * Get all records of the query result.
   *
   * @return DataSet The result set
   *
   * @example ->get()
   */
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
   * Get the first record
   *
   * Get the first record of the query result.
   *
   * @return object|null The first entity or null
   *
   * @example ->first()
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
   *
   * @example ->find(1)
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
   *
   * @example ->findOrFail(1)
   */
  public function findOrFail($id): object
  {
    if ($result = $this->find($id)) {
      return $result;
    }

    throw new \RuntimeException("No record found for ID {$id}");
  }

  /**
   * Include soft deleted records
   *
   * @return self
   *
   * @example ->withTrashed()
   */
  public function withTrashed(): self
  {
    $this->withTrashed = true;
    return $this;
  }

  /**
   * Only include soft deleted records
   *
   * @return self
   *
   * @example ->onlyTrashed()
   */
  public function onlyTrashed(): self
  {
    $this->onlyTrashed = true;
    return $this;
  }

  /**
   * Apply soft delete conditions
   *
   * Apply soft delete conditions to the query based on the entity class.
   * If the entity class is not set, or the table does not have a trash column,
   * then the method does nothing. If the query is set to only include trashed
   * records, then the method adds a WHERE condition to the query where the
   * trash column is not null. If the query is set to include trashed records,
   * then the method adds a WHERE condition to the query where the trash column
   * is null.
   * @return void
   */
  protected function applySoftDeleteConditions(): void
  {
    // If no entity class is set, do nothing
    if ($this->entityClass === null) {
      return;
    }

    // Get the trash column name from the entity class
    $trashColumn = $this->getEntityTrashColumn();

    // If the table does not have the trash column, do nothing
    if (!Schema::tableHasColumn($this->table, $trashColumn)) return;

    // If the query is set to only include trashed records, add a WHERE condition
    // to the query where the trash column is not null
    if ($this->onlyTrashed) {
      $this->whereNotNull($trashColumn);
    }
    // If the query is set to include trashed records, add a WHERE condition
    // to the query where the trash column is null
    elseif (!$this->withTrashed) {
      $this->whereNull($trashColumn);
    }
  }

  /**
   * Get the trash column name from the entity class
   *
   * Get the trash column name from the entity class.
   * Return the value of the $trashColumn property from the entity class,
   * or 'trashed_at' if the entity class does not have this property.
   * @return string The trash column name
   *
   * @example $this->getEntityTrashColumn()
   */
  private function getEntityTrashColumn(): string
  {
    // Default trash column name
    $default = 'trashed_at';

    // If no entity class is set, return the default
    if (!$this->entityClass || !class_exists($this->entityClass)) {
      return $default;
    }

    // Get the trash column name from the entity class property
    // If the property does not exist, return the default
    try {
      $reflection = new \ReflectionClass($this->entityClass);
      $property = $reflection->getProperty('trashColumn');
      // Bypass visibility
      $property->setAccessible(true);
      return $property->getValue() ?? $default;
    } catch (\ReflectionException $e) {
      return $default;
    }
  }

  // SQL COMPILATION

  /**
   * Compile the SELECT statement
   *
   * Compile the SELECT statement based on the query builder's properties.
   * This method builds the SQL query string for the SELECT statement.
   *
   * @return string The compiled SQL query
   *
   * @example $this->compileSelect()
   */
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

  /**
   * Compile the WHERE clauses
   *
   * Compile the WHERE clauses based on the query builder's properties.
   * This method builds the SQL query string for the WHERE clauses.
   *
   * @return string The compiled SQL query
   *
   * @example $this->compileWheres()
   */
  protected function compileWheres(): string
  {
    if (empty($this->wheres)) return '';

    // Build WHERE clauses
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

      // Handle different types of WHERE conditions
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

  /**
   * Execute the query and return the result set
   *
   * Execute the query and return the result set.
   * This method builds the SQL query string and executes it.
   *
   * @param string $sql The SQL query string
   *
   * @return DataSet The result set
   *
   * @example $this->executeQuery('SELECT * FROM users')
   */
  protected function executeQuery(string $sql): DataSet
  {
    // Execute the query
    $stmt = $this->executeStatement($sql, $this->bindings);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Hydrate entities if entity class is set
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

  /**
   * Execute a statement
   *
   * Execute a statement.
   * This method builds the SQL query string and executes it.
   *
   * @param string $sql The SQL query string
   * @param array $bindings The bindings for the query
   *
   * @return PDOStatement The executed statement
   *
   * @example $this->executeStatement('SELECT * FROM users')
   */
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

  /**
   * Get the SQL query string
   *
   * Get the SQL query string.
   * This method builds the SQL query string for the current query.
   *
   * @return string The SQL query string
   *
   * @example $this->toSql()
   */
  public function toSql(): string
  {
    // Build SQL
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

  /**
   * Compile the INSERT statement
   *
   * Compile the INSERT statement based on the query builder's properties.
   * This method builds the SQL query string for the INSERT statement.
   *
   * @return string The compiled SQL query
   *
   * @example $this->compileInsert()
   */
  protected function compileInsert(): string
  {
    $columns = implode(', ', array_map(fn($col) => "`$col`", array_keys($this->insertData)));
    $placeholders = implode(', ', array_fill(0, count($this->insertData), '?'));
    return "INSERT INTO `{$this->table}` ($columns) VALUES ($placeholders)";
  }

  /**
   * Compile the UPDATE statement
   *
   * Compile the UPDATE statement based on the query builder's properties.
   * This method builds the SQL query string for the UPDATE statement.
   *
   * @return string The compiled SQL query
   *
   * @example $this->compileUpdate()
   */
  protected function compileUpdate(): string
  {
    $set = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($this->updateData)));
    return "UPDATE `{$this->table}` SET $set " . $this->compileWheres();
  }

  /**
   * Compile the DELETE statement
   *
   * Compile the DELETE statement based on the query builder's properties.
   * This method builds the SQL query string for the DELETE statement.
   *
   * @return string The compiled SQL query
   *
   * @example $this->compileDelete()
   */
  protected function compileDelete(): string
  {
    return "DELETE FROM `{$this->table}` " . $this->compileWheres();
  }

  public function getBindings(): array
  {
    return $this->bindings;
  }
}
