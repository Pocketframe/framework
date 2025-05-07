<?php

namespace Pocketframe\PocketORM\QueryEngine;

use Closure;
use InvalidArgumentException;
use PDO;
use PDOException;
use Pocketframe\PocketORM\Concerns\DeepFetch;
use Pocketframe\PocketORM\Database\Connection;
use Pocketframe\PocketORM\Entity\Entity;
use Pocketframe\PocketORM\Essentials\DataSet;
use Pocketframe\PocketORM\Pagination\CursorPaginator;
use Pocketframe\PocketORM\Pagination\Paginator;
use Pocketframe\PocketORM\Concerns\Trashable;

class QueryEngine
{
  use DeepFetch;

  protected string $table               = '';
  protected array $select               = ['*'];
  protected array $wheres               = [];
  protected array $joins                = [];
  protected array $groups               = [];
  protected array $havings              = [];
  protected array $orders               = [];
  protected ?int $limit                 = null;
  protected array $bindings             = [];
  protected array $insertData           = [];
  protected array $updateData           = [];
  protected bool $isDelete              = false;
  protected array $rawSelects           = [];
  protected ?string $keyByColumn        = null;
  protected bool $withTrashed           = false;
  protected bool $onlyTrashed           = false;
  protected ?int $offset                = null;
  protected static bool $loggingEnabled = false;
  protected static array $queryLog      = [];
  public array $disabledGlobalScopes = [];
  protected array $executedSql          = [];
  private ?string $entityClass;
  protected float $timeStart = 0.0;
  protected bool $distinct = false;

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
    // 1) Figure out the entity class and table name
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

    // 2) Auto‑boot any bootXYZ() methods (e.g. bootTrashable())
    if ($this->entityClass) {
      $this->entityClass::runBootMethods();
    }
  }

  /**
   * Static factory method for building a new QueryEngine instance.
   *
   * @param mixed $entity An Entity class name, instance, or table name string.
   * @return self
   */
  public static function for(string $entity): self
  {
    $entity::bootIfNotBooted();
    $instance = new static($entity);
    $instance->entityClass = $entity;
    return $instance;
  }

  public function scope(string $name, ...$args): self
  {
    $scopeMethod = 'scope' . ucfirst($name);
    if (method_exists($this->entityClass, $scopeMethod)) {
      array_unshift($args, $this);
      return call_user_func_array([$this->entityClass, $scopeMethod], $args);
    }
    throw new \BadMethodCallException("Scope '$name' not defined on {$this->entityClass}. You can define it in the entity class or check the spelling.");
  }


  protected function applyTenantScope()
  {
    if (config('tenant.enabled') === true) {
      if (in_array(\Pocketframe\PocketORM\Concerns\TenantAware::class, class_uses($this->entityClass))) {
        $tenantId = $this->entityClass::getTenantId();
        dd($tenantId);
        if ($tenantId !== null) {
          $this->where($this->entityClass::tenantColumn(), '=', $tenantId);
        }
      }
    }
  }

  /*
  |------------------------------------------------
  | FETCHING RECORDS
  |***********************************************
  | Fetch the records of the query result.
  |----------------------------------------------

  */

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
    $this->applyGlobalScopes();
    $this->applyTenantScope();
    $this->applyTrashableConditions();

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
    $this->applyGlobalScopes();
    $this->applyTenantScope();
    $this->applyTrashableConditions();

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
  public function find(int|string $id): ?object
  {
    $this->applyGlobalScopes();
    $this->applyTenantScope();
    $this->applyTrashableConditions();
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
  public function findOrFail(int|string $id): object
  {
    if ($result = $this->find($id)) {
      return $result;
    }

    throw new \RuntimeException("No record found for ID {$id}");
  }

  /*
  |------------------------------------------------
  | TRASHING
  |***********************************************
  | Manage soft-deleted records.
  |----------------------------------------------
   */

  /**
   * Include trash records
   *
   * @return self
   *
   * @example ->withTrashed()
   */
  public function withTrashed(): self
  {
    $this->withTrashed = true;
    $this->removeGlobalScope('excludeTrash');
    return $this;
  }

  /**
   * Only include trash records
   *
   * @return self
   *
   * @example ->onlyTrashed()
   */
  public function onlyTrashed(): self
  {
    $this->removeGlobalScope('excludeTrash');
    $this->onlyTrashed = true;
    return $this;
  }

  /*
  |------------------------------------------------
  | SELECTING
  |***********************************************
  | Select columns from the database.
  |----------------------------------------------
   */

  /**
   * Add columns to SELECT clause
   *
   * @param array $columns Array of column names
   * @return self
   *
   * @example ->select(['id', 'name', 'email'])
   * @example ->select(['*'])
   */
  public function select(array $columns): self
  {
    $this->select = $columns;
    return $this;
  }


  /**
   * Set the query to select distinct rows.
   *
   * @return self
   *
   * @example ->distinct()
   */
  public function distinct(): self
  {
    $this->distinct = true;
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
   * Set the table for the query (useful for subqueries).
   *
   * @param string $table The table name
   * @return self
   *
   * @example $query->from('orders')
   */
  public function from(string $table): self
  {
    $this->table = $table;
    return $this;
  }

  /**
   * Add a subquery select clause
   *
   * @param string $alias Alias for the subquery result
   * @param Closure|QueryEngine $query Subquery instance or closure
   * @return self
   *
   * @example selectSub('total_posts', function($q) {
   *     $q->from('posts')->selectRaw('COUNT(*)')->whereColumn('user_id', 'users.id');
   * })
   * SELECT (SELECT COUNT(*) FROM posts WHERE user_id = users.id) AS total_posts ...
   */
  public function selectSub(string $alias, $query): self
  {
    if ($query instanceof Closure) {
      $sub = new self($this->table);
      $query($sub);
    } elseif ($query instanceof self) {
      $sub = $query;
    } else {
      throw new \InvalidArgumentException('Subquery must be a Closure or QueryEngine instance');
    }

    $this->select[] = "({$sub->toSql()}) AS {$alias}";
    $this->bindings = array_merge($this->bindings, $sub->getBindings());
    return $this;
  }

  /**
   * Add a subquery from clause
   *
   * @param string $alias Table alias
   * @param Closure|QueryEngine $query Subquery instance or closure
   * @return self
   *
   * @example fromSub('user_stats', function($q) {
   *     $q->from('users')
   *       ->select(['id', 'posts_count' => fn($q) => $q->from('posts')->whereColumn('user_id', 'users.id')->selectRaw('COUNT(*)')]);
   * })
   * SELECT * FROM (SELECT id, (SELECT COUNT(*) FROM posts WHERE user_id = users.id) AS posts_count FROM users) AS user_stats
   */
  public function fromSub(string $alias, $query): self
  {
    if ($query instanceof Closure) {
      $sub = new self($this->table);
      $query($sub);
    } elseif ($query instanceof self) {
      $sub = $query;
    } else {
      throw new \InvalidArgumentException('Subquery must be a Closure or QueryEngine instance');
    }

    $this->table = "({$sub->toSql()}) AS {$alias}";
    $this->bindings = array_merge($sub->getBindings(), $this->bindings);
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


  /*
   |------------------------------------------------
   | WHERE
   |***********************************************
   | Add conditions to the query.
   |----------------------------------------------
   */

  /**
   * Add a basic WHERE condition
   *
   * Add a basic WHERE condition to the query.
   *
   * @param string|Closure $column Column name
   * @param string $operator Comparison operator
   * @param mixed $value Value to compare
   * @param string $boolean Logical operator ('AND' or 'OR')
   * @return self
   *
   * @example ->where('id', '=', 1)
   * @example ->where('name', 'like', '%John%')
   */
  public function where(string|Closure $column, ?string $operator = null, $value = null, string $boolean = 'AND'): self
  {
    if (func_num_args() === 2) {
      $value = $operator;
      $operator = '=';
    } elseif ($value === null) {
      $value = $operator;
      $operator = '=';
    }

    if ($column instanceof Closure) {
      // Start a grouped where
      $query = new self($this->table);
      $column($query); // $column is the closure
      $this->wheres[] = [
        'type' => 'nested',
        'query' => $query,
        'boolean' => $boolean,
      ];
      $this->bindings = array_merge($this->bindings, $query->bindings);
      return $this;
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
   * @param string|Closure $column Column name
   * @param string $operator Comparison operator
   * @param mixed $value Value to compare
   * @return self
   *
   * @example ->orWhere('name', 'like', '%John%')
   */
  public function orWhere(string|Closure $column, ?string $operator = null, $value = null): self
  {
    return $this->where($column, $operator, $value, 'OR');
  }


  /**
   * Add a WHERE NOT condition
   *
   * Add a WHERE NOT condition to the query.
   *
   * @param Closure $callback Callback function to define the nested query
   * @param string $boolean Logical operator ('AND' or 'OR')
   * @return self
   *
   * @example ->whereNot(function ($query) {
   *   $query->where('name', 'John');
   * })
   * @example ->whereNot(function($q) {
   *   $q->where('role', 'admin')
   *     ->where('email_verified', 0);
   * })
   */
  public function whereNot(Closure $callback, string $boolean = 'AND'): self
  {
    $query = new self($this->table);
    $callback($query);
    $this->wheres[] = [
      'type' => 'nested',
      'query' => $query,
      'boolean' => $boolean,
      'not' => true,
    ];
    $this->bindings = array_merge($this->bindings, $query->bindings);
    return $this;
  }

  /**
   * Add an OR WHERE NOT condition
   *
   * Add an OR WHERE NOT condition to the query.
   *
   * @param Closure $callback Callback function to define the nested query
   * @return self
   *
   * @example ->orWhereNot(function ($query) {
   *   $query->where('name', 'John');
   * })
   */
  public function orWhereNot(Closure $callback): self
  {
    return $this->whereNot($callback, 'OR');
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
   * Add a WHERE NOT IN condition
   *
   * Add a WHERE NOT IN condition to the query.
   *
   * @param string $column Column name
   * @param array $values Array of values to compare
   * @param string $boolean Logical operator ('AND' or 'OR')
   * @return self
   *
   * @example ->whereNotIn('status', ['banned', 'suspended'])
   * @example ->orWhereNotIn('category_id', [5, 9])
   */
  public function whereNotIn(string $column, array $values, string $boolean = 'AND'): self
  {
    return $this->whereIn($column, $values, $boolean, true);
  }

  /**
   * Add an OR WHERE NOT IN condition
   *
   * Add an OR WHERE NOT IN condition to the query.
   *
   * @param string $column Column name
   * @param array $values Array of values to compare
   * @return self
   *
   * @example ->orWhereNotIn('id', [1, 2, 3])
   */
  public function orWhereNotIn(string $column, array $values): self
  {
    return $this->whereIn($column, $values, 'OR', true);
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

  /**
   * Add a WHERE JSON_CONTAINS condition
   *
   * Add a WHERE JSON_CONTAINS condition to the query.
   *
   * @param string $column Column name
   * @param mixed $value Value to compare
   * @param string $boolean Logical operator ('AND' or 'OR')
   * @return self
   *
   * @example ->whereContainsJson('preferences->notifications', 'email')
   * @example ->orWhereContainsJson('preferences->alerts', 'sms')
   */
  public function whereContainsJson(string $column, $value, string $boolean = 'AND'): self
  {
    if (is_array($value)) {
      return $this->handleJsonArrayContains($column, $value, $boolean);
    }

    $this->wheres[] = [
      'type' => 'json_contains',
      'column' => $column,
      'value' => $value,
      'boolean' => $boolean,
    ];
    $this->addBinding($value);

    return $this;
  }

  /**
   * Add an OR WHERE JSON_CONTAINS condition
   *
   * Add an OR WHERE JSON_CONTAINS condition to the query.
   *
   * @param string $column Column name
   * @param mixed $value Value to compare
   * @return self
   *
   * @example ->orWhereContainsJson('tags', 'tag1')
   */
  public function orWhereContainsJson(string $column, $value): self
  {
    return $this->whereContainsJson($column, $value, 'OR');
  }

  /**
   * Add a WHERE JSON_CONTAINS condition for an array of values
   *
   * Add a WHERE JSON_CONTAINS condition to the query for an array of values.
   *
   * @param string $column Column name
   * @param array $values Array of values to compare
   * @param string $boolean Logical operator ('AND' or 'OR')
   * @return self
   *
   * @example ->whereContainsJson('preferences->notifications', ['email', 'sms'])
   * @example ->orWhereContainsJson('preferences->alerts', ['email', 'sms'])
   */
  protected function handleJsonArrayContains(string $column, array $values, string $boolean): self
  {
    $this->wheres[] = [
      'type' => 'json_contains_array',
      'column' => $column,
      'values' => $values,
      'boolean' => $boolean,
    ];

    // Add each value as separate binding
    foreach ($values as $val) {
      $this->addBinding($val);
    }

    return $this;
  }

  /**
   * Add value(s) to the query bindings
   *
   * @param mixed $value Value or array of values to bind
   * @return void
   */
  protected function addBinding($value): void
  {
    if (is_array($value)) {
      $this->bindings = array_merge($this->bindings, array_values($value));
      return;
    }

    $this->bindings[] = $value;
  }


  /**
   * Add a WHERE ANY condition across multiple columns with the same operator and value.
   *
   * @param array $columns The columns to check
   * @param string $operator The comparison operator (e.g., '=', '!=', 'LIKE')
   * @param mixed $value The value to compare against
   * @param string $boolean Logical operator ('AND' or 'OR')
   * @return self
   *
   * @example ->whereAny(['name', 'status', 'created_at'], '=', 'active')
   */
  public function whereAny(array $columns, string $operator, $value, string $boolean = 'AND'): self
  {
    if (empty($columns)) {
      throw new InvalidArgumentException('Columns array for whereAny cannot be empty.');
    }

    // Build nested OR conditions for each column
    return $this->whereGroup(function ($query) use ($columns, $operator, $value) {
      foreach ($columns as $column) {
        $query->orWhere($column, $operator, $value);
      }
    }, $boolean);
  }

  /**
   * Add an OR WHERE ANY condition across multiple columns with the same operator and value.
   *
   * @param array $columns The columns to check
   * @param string $operator The comparison operator (e.g., '=', '!=', 'LIKE')
   * @param mixed $value The value to compare against
   * @return self
   *
   * @example ->orWhereAny(['name', 'status'], '=', 'active')
   */
  public function orWhereAny(array $columns, string $operator, $value): self
  {
    return $this->whereAny($columns, $operator, $value, 'OR');
  }

  /**
   * Add a WHERE ALL condition: all columns must match the value with the operator.
   *
   * @param array $columns Columns to check
   * @param string $operator Comparison operator
   * @param mixed $value Value to compare
   * @param string $boolean Logical operator ('AND' or 'OR')
   * @return self
   *
   * @example ->whereAll(['role', 'status'], '=', 'active')
   */
  public function whereAll(array $columns, string $operator, $value, string $boolean = 'AND'): self
  {
    if (empty($columns)) {
      throw new InvalidArgumentException('Columns array for whereAll cannot be empty.');
    }
    // All columns must match (AND)
    return $this->whereGroup(function ($query) use ($columns, $operator, $value) {
      foreach ($columns as $column) {
        $query->where($column, $operator, $value);
      }
    }, $boolean);
  }

  /**
   * Add an OR WHERE ALL condition
   *
   * @param array $columns
   * @param string $operator
   * @param mixed $value
   * @return self
   *
   * @example ->orWhereAll(['role', 'status'], '=', 'active')
   */
  public function orWhereAll(array $columns, string $operator, $value): self
  {
    return $this->whereAll($columns, $operator, $value, 'OR');
  }

  /**
   * Add a WHERE NONE condition: all columns must NOT match the value with the operator.
   *
   * @param array $columns Columns to check
   * @param string $operator Comparison operator
   * @param mixed $value Value to compare
   * @param string $boolean Logical operator ('AND' or 'OR')
   * @return self
   *
   * @example ->whereNone(['role', 'status'], '=', 'banned')
   */
  public function whereNone(array $columns, string $operator, $value, string $boolean = 'AND'): self
  {
    if (empty($columns)) {
      throw new InvalidArgumentException('Columns array for whereNone cannot be empty.');
    }
    // All columns must NOT match (AND between NOTs)
    return $this->whereGroup(function ($query) use ($columns, $operator, $value) {
      foreach ($columns as $column) {
        $query->where($column, $operator, $value)->not();
        // or, if you have a whereNot method:
        // $query->whereNot(function($q) use ($column, $operator, $value) {
        //     $q->where($column, $operator, $value);
        // });
      }
    }, $boolean);
  }

  /**
   * Add an OR WHERE NONE condition
   *
   * @param array $columns
   * @param string $operator
   * @param mixed $value
   * @return self
   *
   * @example ->orWhereNone(['role', 'status'], '=', 'banned')
   */
  public function orWhereNone(array $columns, string $operator, $value): self
  {
    return $this->whereNone($columns, $operator, $value, 'OR');
  }

  /**
   * Add a WHERE LIKE condition
   *
   * Add a WHERE LIKE condition to the query.
   *
   * @param string $column Column name
   * @param string $value Value to compare
   * @param bool $caseSensitive Case sensitivity flag
   * @param string $boolean Logical operator ('AND' or 'OR')
   * @return self
   *
   * @example ->whereLike('username', 'Admin', true)
   * @example ->orWhereLike('filename', '.PDF', true)
   *
   *  Case-insensitive search
   * @example ->whereNotLike('email', '%@spam.com')
   * @example ->orWhereNotLike('title', '%test%', false)
   */
  public function whereLike(string $column, string $value, bool $caseSensitive = false, string $boolean = 'AND'): self
  {
    $operator = $caseSensitive ? 'LIKE BINARY' : 'LIKE';

    $this->wheres[] = [
      'type'     => 'basic',
      'column'   => $column,
      'operator' => $operator,
      'value'    => $value,
      'boolean'  => $boolean,
    ];

    $this->addBinding($value);
    return $this;
  }

  /**
   * Add an OR WHERE LIKE condition
   *
   * Add an OR WHERE LIKE condition to the query.
   *
   * @param string $column Column name
   * @param string $value Value to compare
   * @param bool $caseSensitive Case sensitivity flag
   * @return self
   *
   * @example ->orWhereLike('name', 'John')
   */
  public function orWhereLike(string $column, string $value, bool $caseSensitive = false): self
  {
    return $this->whereLike($column, $value, $caseSensitive, 'OR');
  }

  /**
   * Add a WHERE NOT LIKE condition
   *
   * Add a WHERE NOT LIKE condition to the query.
   *
   * @param string $column Column name
   * @param string $value Value to compare
   * @param bool $caseSensitive Case sensitivity flag
   * @param string $boolean Logical operator ('AND' or 'OR')
   * @return self
   *
   * @example ->whereNotLike('name', 'John')
   * @example ->whereNotLike('name', 'John', 'OR')
   */
  public function whereNotLike(string $column, string $value, bool $caseSensitive = false, string $boolean = 'AND'): self
  {
    $operator = $caseSensitive ? 'NOT LIKE BINARY' : 'NOT LIKE';
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
   * Add an OR WHERE NOT LIKE condition
   *
   * Add an OR WHERE NOT LIKE condition to the query.
   *
   * @param string $column Column name
   * @param string $value Value to compare
   * @param bool $caseSensitive Case sensitivity flag
   * @return self
   *
   * @example ->orWhereNotLike('name', 'John')
   */
  public function orWhereNotLike(string $column, string $value, bool $caseSensitive = false): self
  {
    return $this->whereNotLike($column, $value, $caseSensitive, 'OR');
  }


  /**
   * Add a WHERE BETWEEN condition
   *
   * Add a WHERE BETWEEN condition to the query.
   *
   * @param string $column Column name
   * @param mixed $start Start value
   * @param mixed $end End value
   * @param string $boolean Logical operator ('AND' or 'OR')
   * @param bool $not Whether to use NOT BETWEEN
   * @return self
   *
   * value range
   * @example ->whereBetween('price', 100, 500)
   * @example ->orWhereBetween('created_at', '2023-01-01', '2023-12-31')
   */
  public function whereBetween(string $column, $start, $end, string $boolean = 'AND', bool $not = false): self
  {
    $this->wheres[] = [
      'type' => 'between',
      'column' => $column,
      'start' => $start,
      'end' => $end,
      'boolean' => $boolean,
      'not' => $not,
    ];
    $this->bindings[] = $start;
    $this->bindings[] = $end;
    return $this;
  }

  /**
   * Add an OR WHERE BETWEEN condition
   *
   * Add an OR WHERE BETWEEN condition to the query.
   *
   * @param string $column Column name
   * @param mixed $start Start value
   * @param mixed $end End value
   * @return self
   *
   * @example ->orWhereBetween('id', 1, 10)
   */
  public function orWhereBetween(string $column, $start, $end): self
  {
    return $this->whereBetween($column, $start, $end, 'OR');
  }

  /**
   * Add a WHERE NOT BETWEEN condition
   *
   * Add a WHERE NOT BETWEEN condition to the query.
   *
   * @param string $column Column name
   * @param mixed $start Start value
   * @param mixed $end End value
   * @param string $boolean Logical operator ('AND' or 'OR')
   * @return self
   *
   * @example ->whereNotBetween('age', 13, 20)
   * @example ->orWhereNotBetween('rating', 1, 3)
   */
  public function whereNotBetween(string $column, $start, $end, string $boolean = 'AND'): self
  {
    return $this->whereBetween($column, $start, $end, $boolean, true);
  }

  /**
   * Add an OR WHERE NOT BETWEEN condition
   *
   * Add an OR WHERE NOT BETWEEN condition to the query.
   *
   * @param string $column Column name
   * @param mixed $start Start value
   * @param mixed $end End value
   * @return self
   *
   * @example ->orWhereNotBetween('id', 1, 10)
   */
  public function orWhereNotBetween(string $column, $start, $end): self
  {
    return $this->whereBetween($column, $start, $end, 'OR', true);
  }

  /**
   * Add a WHERE BETWEEN condition using column names
   *
   * Add a WHERE BETWEEN condition to the query using column names.
   *
   * @param string $column Column name
   * @param string $startColumn Start column name
   * @param string $endColumn End column name
   * @param string $boolean Logical operator ('AND' or 'OR')
   * @param bool $not Whether to use NOT BETWEEN
   * @return self
   *
   * column range
   * @example ->whereBetweenColumn('event_date', 'start_date', 'end_date')
   * @example ->orWhereBetweenColumn('temperature', 'min_temp', 'max_temp')
   */
  public function whereBetweenColumn(string $column, string $startColumn, string $endColumn, string $boolean = 'AND', bool $not = false): self
  {
    $this->wheres[] = [
      'type' => 'between_columns',
      'column' => $column,
      'start' => $startColumn,
      'end' => $endColumn,
      'boolean' => $boolean,
      'not' => $not,
    ];
    return $this;
  }

  /**
   * Add an OR WHERE BETWEEN condition using column names
   *
   * Add an OR WHERE BETWEEN condition to the query using column names.
   *
   * @param string $column Column name
   * @param string $startColumn Start column name
   * @param string $endColumn End column name
   * @return self
   *
   * @example ->orWhereBetweenColumn('id', 'start_id', 'end_id')
   */
  public function orWhereBetweenColumn(string $column, string $startColumn, string $endColumn): self
  {
    return $this->whereBetweenColumn($column, $startColumn, $endColumn, 'OR');
  }

  /*
  |------------------------------------------------
  | ADVANCED WHERE CLAUSES
  |************************************************
  | Add conditions to the query based on advanced conditions
  |-------------------------------------------------
  */

  /**
   * Add a WHERE EXISTS condition
   *
   * Adds a WHERE EXISTS subquery to the query.
   *
   * @param Closure $callback Callback to define the subquery
   * @param string $boolean Logical operator ('AND' or 'OR')
   * @return self
   *
   * @example ->whereExists(function ($query) {
   *   $query->select('*')->from('orders')->whereColumn('orders.user_id', 'users.id');
   * })
   */
  public function whereExists(Closure $callback, string $boolean = 'AND'): self
  {
    $query = new self($this->table);
    $callback($query);
    $this->wheres[] = [
      'type'    => 'exists',
      'query'   => $query,
      'boolean' => $boolean,
    ];
    $this->bindings = array_merge($this->bindings, $query->bindings);
    return $this;
  }

  /**
   * Add an OR WHERE EXISTS condition
   *
   * @param Closure $callback Callback to define the subquery
   * @return self
   *
   * @example ->orWhereExists(function ($query) {
   *   $query->select('*')->from('orders')->whereColumn('orders.user_id', 'users.id');
   * })
   */
  public function orWhereExists(Closure $callback): self
  {
    return $this->whereExists($callback, 'OR');
  }

  /**
   * Add a WHERE NOT EXISTS condition
   *
   * Adds a WHERE NOT EXISTS subquery to the query.
   *
   * @param Closure $callback Callback to define the subquery
   * @param string $boolean Logical operator ('AND' or 'OR')
   * @return self
   *
   * @example ->whereNotExists(function ($query) {
   *   $query->select('*')->from('orders')->whereColumn('orders.user_id', 'users.id');
   * })
   */
  public function whereNotExists(Closure $callback, string $boolean = 'AND'): self
  {
    $query = new self($this->table);
    $callback($query);
    $this->wheres[] = [
      'type'    => 'not_exists',
      'query'   => $query,
      'boolean' => $boolean,
    ];
    $this->bindings = array_merge($this->bindings, $query->bindings);
    return $this;
  }

  /**
   * Add an OR WHERE NOT EXISTS condition
   *
   * @param Closure $callback Callback to define the subquery
   * @return self
   *
   * @example ->orWhereNotExists(function ($query) {
   *   $query->select('*')->from('orders')->whereColumn('orders.user_id', 'users.id');
   * })
   */
  public function orWhereNotExists(Closure $callback): self
  {
    return $this->whereNotExists($callback, 'OR');
  }


  /*
   |------------------------------------------------
   | WHERE Date and/or time
   |***********************************************
   | Add conditions to the query based on date and/or time
   |----------------------------------------------
   */

  /**
   * Add a WHERE DATE condition
   *
   * Add a WHERE DATE condition to the query.
   *
   * @param string $column Column name
   * @param string $operator Comparison operator
   * @param mixed $value Value to compare
   * @param string $boolean Logical operator ('AND' or 'OR')
   * @return self
   *
   * @example ->whereDate('created_at', '=', '2023-05-15')
   * @example ->whereMonth('birthday', '=', 12) // December birthdays
   * @example ->whereDay('event_date', '=', 25) // 25th of any month
   * @example ->whereYear('published_at', '>', 2020)
   * @example ->whereTime('log_time', '>', '18:00:00')
   */
  public function whereDate(string $column, string $operator, $value, string $boolean = 'AND'): self
  {
    $this->wheres[] = [
      'type' => 'date',
      'column' => $column,
      'operator' => $operator,
      'value' => $value,
      'boolean' => $boolean,
    ];
    $this->bindings[] = $value;
    return $this;
  }

  /**
   * Add a WHERE MONTH condition
   *
   * Add a WHERE MONTH condition to the query.
   *
   * @param string $column Column name
   * @param string $operator Comparison operator
   * @param mixed $value Value to compare
   * @param string $boolean Logical operator ('AND' or 'OR')
   * @return self
   *
   * @example ->whereMonth('created_at', '=', 1)
   * @example ->whereMonth('created_at', '=', 1, 'OR')
   */
  public function whereMonth(string $column, string $operator, $value, string $boolean = 'AND'): self
  {
    $this->wheres[] = [
      'type' => 'month',
      'column' => $column,
      'operator' => $operator,
      'value' => $value,
      'boolean' => $boolean,
    ];
    $this->bindings[] = $value;
    return $this;
  }

  /**
   * Add a WHERE DAY condition
   *
   * Add a WHERE DAY condition to the query.
   *
   * @param string $column Column name
   * @param string $operator Comparison operator
   * @param mixed $value Value to compare
   * @param string $boolean Logical operator ('AND' or 'OR')
   * @return self
   *
   * @example ->whereDay('created_at', '=', 1)
   * @example ->whereDay('created_at', '=', 1, 'OR')
   */
  public function whereDay(string $column, string $operator, $value, string $boolean = 'AND'): self
  {
    $this->wheres[] = [
      'type' => 'day',
      'column' => $column,
      'operator' => $operator,
      'value' => $value,
      'boolean' => $boolean,
    ];
    $this->bindings[] = $value;
    return $this;
  }

  /**
   * Add a WHERE YEAR condition
   *
   * Add a WHERE YEAR condition to the query.
   *
   * @param string $column Column name
   * @param string $operator Comparison operator
   * @param mixed $value Value to compare
   * @param string $boolean Logical operator ('AND' or 'OR')
   * @return self
   *
   * @example ->whereYear('created_at', '=', 2023)
   * @example ->whereYear('created_at', '=', 2023, 'OR')
   */
  public function whereYear(string $column, string $operator, $value, string $boolean = 'AND'): self
  {
    $this->wheres[] = [
      'type' => 'year',
      'column' => $column,
      'operator' => $operator,
      'value' => $value,
      'boolean' => $boolean,
    ];
    $this->bindings[] = $value;
    return $this;
  }

  /**
   * Add a WHERE TIME condition
   *
   * Add a WHERE TIME condition to the query.
   *
   * @param string $column Column name
   * @param string $operator Comparison operator
   * @param mixed $value Value to compare
   * @param string $boolean Logical operator ('AND' or 'OR')
   * @return self
   *
   * @example ->whereTime('created_at', '=', '12:00:00')
   * @example ->whereTime('created_at', '=', '12:00:00', 'OR')
   */
  public function whereTime(string $column, string $operator, $value, string $boolean = 'AND'): self
  {
    $this->wheres[] = [
      'type' => 'time',
      'column' => $column,
      'operator' => $operator,
      'value' => $value,
      'boolean' => $boolean,
    ];
    $this->bindings[] = $value;
    return $this;
  }

  /**
   * Add a WHERE condition for today
   *
   * Add a WHERE condition to the query for today.
   *
   * @param string $column Column name
   * @param string $boolean Logical operator ('AND' or 'OR')
   * @return self
   *
   * @example ->whereToday('created_at') // Records from today
   * @example ->whereYesterday('updated_at') // Updated yesterday
   * @example ->whereTomorrow('event_date') // Events scheduled tomorrow
   * @example ->whereNow('timestamp_col') // Exactly current datetime
   * @example ->whereBefore('expiry_date', '2024-01-01')
   * @example ->whereAfter('start_date', '2023-06-01')
   */
  public function whereToday(string $column, string $boolean = 'AND'): self
  {
    return $this->whereDate($column, '=', date('Y-m-d'), $boolean);
  }

  /**
   * Add a WHERE condition for yesterday
   *
   * Add a WHERE condition to the query for yesterday.
   *
   * @param string $column Column name
   * @param string $boolean Logical operator ('AND' or 'OR')
   * @return self
   *
   * @example ->whereYesterday('created_at')
   * @example ->whereYesterday('created_at', 'OR')
   */
  public function whereYesterday(string $column, string $boolean = 'AND'): self
  {
    return $this->whereDate($column, '=', date('Y-m-d', strtotime('-1 day')), $boolean);
  }

  /**
   * Add a WHERE condition for tomorrow
   *
   * Add a WHERE condition to the query for tomorrow.
   *
   * @param string $column Column name
   * @param string $boolean Logical operator ('AND' or 'OR')
   * @return self
   *
   * @example ->whereTomorrow('created_at')
   * @example ->whereTomorrow('created_at', 'OR')
   */
  public function whereTomorrow(string $column, string $boolean = 'AND'): self
  {
    return $this->whereDate($column, '=', date('Y-m-d', strtotime('+1 day')), $boolean);
  }

  /**
   * Add a WHERE condition for the current time
   *
   * Add a WHERE condition to the query for the current time.
   *
   * @param string $column Column name
   * @param string $boolean Logical operator ('AND' or 'OR')
   * @return self
   *
   * @example ->whereNow('created_at')
   * @example ->whereNow('created_at', 'OR')
   */
  public function whereNow(string $column, string $boolean = 'AND'): self
  {
    return $this->where($column, '=', date('Y-m-d H:i:s'), $boolean);
  }

  /**
   * Add a WHERE condition for a value before a given value
   *
   * Add a WHERE condition to the query for a value before a given value.
   *
   * @param string $column Column name
   * @param mixed $value Value to compare
   * @param string $boolean Logical operator ('AND' or 'OR')
   * @return self
   *
   * @example ->whereBefore('created_at', '2023-01-01')
   * @example ->whereBefore('created_at', '2023-01-01', 'OR')
   */
  public function whereBefore(string $column, $value, string $boolean = 'AND'): self
  {
    return $this->where($column, '<', $value, $boolean);
  }

  /**
   * Add a WHERE condition for a value after a given value
   *
   * Add a WHERE condition to the query for a value after a given value.
   *
   * @param string $column Column name
   * @param mixed $value Value to compare
   * @param string $boolean Logical operator ('AND' or 'OR')
   * @return self
   *
   * @example ->whereAfter('created_at', '2023-01-01')
   * @example ->whereAfter('created_at', '2023-01-01', 'OR')
   */
  public function whereAfter(string $column, $value, string $boolean = 'AND'): self
  {
    return $this->where($column, '>', $value, $boolean);
  }

  /**
   * Add a WHERE group
   *
   * Add a WHERE group to the query.
   *
   * @param Closure $callback Callback function to define the group
   * @param string $boolean Logical operator ('AND' or 'OR')
   * @return self
   *
   * @example ->whereGroup(function ($query) {
   *   $query->where('id', '>', 100)
   *         ->orWhere('id', '<', 50);
   * })
   * @example ->whereGroup(function ($query) {
   *   $query->where('id', '>', 100)
   *         ->orWhere('id', '<', 50);
   * }, 'OR')
   *
   * @example ->whereGroup(function($query) {
   *   $query->where('status', 'active')
   *     ->where('credit_score', '>', 700);
   * }, 'AND')
   * @example ->orWhereGroup(function($query) {
   *   $query->where('legacy_user', 1)
   *     ->whereNull('deleted_at');
   * })
   * WHERE (status = 'active' AND credit_score > 700)
   * OR (legacy_user = 1 AND deleted_at IS NULL)
   */
  public function whereGroup(Closure $callback, string $boolean = 'AND'): self
  {
    $query = new self($this->table);
    $callback($query);
    $this->wheres[] = [
      'type' => 'nested',
      'query' => $query,
      'boolean' => $boolean,
    ];
    $this->bindings = array_merge($this->bindings, $query->bindings);
    return $this;
  }

  /**
   * Add an OR WHERE group
   *
   * Add an OR WHERE group to the query.
   *
   * @param Closure $callback Callback function to define the group
   * @return self
   *
   * @example ->orWhereGroup(function ($query) {
   *   $query->where('id', '>', 100)
   *         ->orWhere('id', '<', 50);
   * })
   */
  public function orWhereGroup(Closure $callback): self
  {
    return $this->whereGroup($callback, 'OR');
  }

  /*
   |------------------------------------------------
   | JOIN
   |***********************************************
   | Add a join condition
   |----------------------------------------------
   */

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
   * @example ->join('users', 'id', '=', 'user_id', 'LEFT')
   */
  public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
  {
    $this->joins[] = "$type JOIN $table ON $first $operator $second";
    return $this;
  }

  /*
   |------------------------------------------------
   | GROUPING, ORDERING, LIMITING
   |***********************************************
   | Add a group by condition
   |----------------------------------------------
   */

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
  public function byDesc(string $column = 'id'): self
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
  public function byAsc(string $column = 'id'): self
  {
    return $this->orderBy($column, 'ASC');
  }

  /**
   * Add a raw ORDER BY clause
   *
   * @param string $expression Raw SQL expression
   * @return self
   *
   * @example ->orderByRaw('RAND()')
   * @example ->orderByRaw('(score + bonus) DESC')
   */
  public function orderByRaw(string $expression): self
  {
    $this->orders[] = $expression;
    return $this;
  }

  /**
   * Order results randomly
   *
   * @return self
   *
   * @example randomOrder() // ORDER BY RAND()
   */
  public function randomOrder(): self
  {
    return $this->orderByRaw('RAND()');
  }

  /**
   * Order by the latest created date
   *
   * @param string|null $column Column to order by (default: created_at)
   * @return self
   *
   * @example latest() // ORDER BY created_at DESC
   * @example latest('updated_at') // ORDER BY updated_at DESC
   */
  public function latest(?string $column = null): self
  {
    $this->applyGlobalScopes();
    $this->applyTenantScope();
    $this->applyTrashableConditions();
    $column = $column ?? 'created_at';
    return $this->orderBy($column, 'DESC');
  }

  /**
   * Order by the oldest created date
   *
   * @param string|null $column Column to order by (default: created_at)
   * @return self
   *
   * @example oldest() // ORDER BY created_at ASC
   * @example oldest('updated_at') // ORDER BY updated_at ASC
   */
  public function oldest(?string $column = null): self
  {
    $this->applyGlobalScopes();
    $this->applyTenantScope();
    $this->applyTrashableConditions();
    $column = $column ?? 'created_at';
    return $this->orderBy($column, 'ASC');
  }


  /**
   * Set the limit and offset for the query (alias for limit/offset)
   *
   * @param int $limit Number of records to return
   * @param int|null $offset Number of records to skip
   * @return self
   *
   * @example limit(10, 5) // LIMIT 10 OFFSET 5
   */
  public function limit(int $limit, ?int $offset = null): self
  {
    $this->limit = $limit;
    if ($offset !== null) {
      $this->offset($offset);
    }
    return $this;
  }

  /**
   * Alias for limit()
   *
   * @param int $limit Number of records to return
   * @return self
   */
  public function take(int $limit): self
  {
    return $this->limit($limit);
  }

  /**
   * Set the offset for the query
   *
   * @param int $offset Number of records to skip
   * @return self
   *
   * @example offset(5) // OFFSET 5
   */
  public function offset(int $offset): self
  {
    $this->offset = $offset;
    return $this;
  }

  /**
   * Alias for offset()
   *
   * @param int $offset Number of records to skip
   * @return self
   */
  public function skip(int $offset): self
  {
    return $this->offset($offset);
  }

  /*
   |------------------------------------------------
   | CONDITIONAL QUERIES
   |***********************************************
   | Add conditional queries to the query engine
   |----------------------------------------------
   */

  /**
   * Conditionally apply query modifications
   *
   * @param mixed $condition Boolean condition
   * @param Closure $callback Callback to apply when true
   * @param Closure|null $default Optional default callback when false
   * @return self
   *
   * @example when($request->has('search'), function($q) use ($request) {
   *     $q->where('name', 'like', "%{$request->search}%");
   * })
   */
  public function when($condition, Closure $callback, ?Closure $default = null): self
  {
    if ($condition) {
      $callback($this);
    } elseif ($default) {
      $default($this);
    }
    return $this;
  }

  /**
   * Conditionally add a WHERE clause (supports closures for nested conditions)
   *
   * @param bool $condition Condition to check
   * @param string|Closure $column Column name or closure for nested where
   * @param mixed|null $operator Operator or value
   * @param mixed|null $value Comparison value
   * @param string $boolean Logical operator
   * @return self
   *
   * @example whereIf($isAdmin, 'role', 'admin')
   * @example whereIf($isActive, function($q) { $q->where('status', 'active')->orWhere('status', 'pending'); })
   */
  public function whereIf(bool $condition, string|Closure $column, $operator = null, $value = null, string $boolean = 'AND'): self
  {
    if ($condition) {
      if ($column instanceof Closure) {
        return $this->where($column, $boolean);
      }
      return $this->where($column, $operator, $value, $boolean);
    }
    return $this;
  }

  /**
   * Conditionally add an OR WHERE clause (supports closures for nested conditions)
   *
   * @param bool $condition Condition to check
   * @param string|Closure $column Column name or closure for nested where
   * @param mixed|null $operator Operator or value
   * @param mixed|null $value Comparison value
   * @return self
   */
  public function orWhereIf(bool $condition, $column, $operator = null, $value = null): self
  {
    if ($condition) {
      if ($column instanceof Closure) {
        return $this->orWhere($column);
      }
      return $this->orWhere($column, $operator, $value);
    }
    return $this;
  }

  /**
   * Add a WHERE HAS (EXISTS) condition for a relationship.
   *
   * @param string $relation The name of the relation (e.g., 'posts')
   * @param Closure $callback The callback to apply conditions to the related query
   * @param string $boolean Logical operator ('AND' or 'OR')
   * @return self
   */
  public function whereHas(string $relation, Closure $callback, string $boolean = 'AND'): self
  {
    $relatedTable = $relation;
    $foreignKey = $this->table . '_id';
    $localKey = 'id';

    return $this->whereExists(function ($q) use ($relatedTable, $foreignKey, $localKey, $callback) {
      $q->from($relatedTable)
        ->whereColumn("{$relatedTable}.{$foreignKey}", "{$this->table}.{$localKey}");
      $callback($q);
    }, $boolean);
  }

  /**
   * Add a WHERE NOT EXISTS condition for a relationship.
   *
   * @param string $relation The name of the relation (e.g., 'posts')
   * @param Closure $callback The callback to apply conditions to the related query
   * @param string $boolean Logical operator ('AND' or 'OR')
   * @return self
   *
   * @example whereDoesntHave('posts', function ($q) {
   *   $q->where('status', '=', 'draft');
   * })
   */
  public function whereDoesntHave(string $relation, Closure $callback, string $boolean = 'AND'): self
  {
    $relatedTable = $relation;
    $foreignKey = $this->table . '_id';
    $localKey = 'id';

    return $this->whereNotExists(function ($q) use ($relatedTable, $foreignKey, $localKey, $callback) {
      $q->from($relatedTable)
        ->whereColumn("{$relatedTable}.{$foreignKey}", "{$this->table}.{$localKey}");
      $callback($q);
    }, $boolean);
  }


  /*
   |------------------------------------------------
   | AGGREGATE QUERIES
   |***********************************************
   | Add aggregate queries to the query engine
   |----------------------------------------------
   */


  /**
   * Retrieve the count of records matching the query
   *
   * @param string $column Column to count (default: all rows)
   * @return int Number of matching records
   *
   * @example count() // Returns total users
   * @example where('active', 1)->count() // Count active users
   * @example count('email') // Count non-null emails
   */
  public function count(string $column = '*'): int
  {
    $this->applyGlobalScopes();
    $this->applyTenantScope();
    $this->applyTrashableConditions();

    // Build the COUNT query
    $query = clone $this;
    $query->selectRaw("COUNT($column) AS aggregate");
    $query->limit  = null;
    $query->offset = null;

    $sql  = $query->compileSelect();
    // executeStatement returns the raw PDOStatement
    $stmt = $this->executeStatement($sql, $this->bindings);

    // fetchOne as associative
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return isset($row['aggregate'])
      ? (int)$row['aggregate']
      : 0;
  }

  /**
   * Calculate an aggregate function (e.g., sum, avg, max, min)
   *
   * @param string $fn Aggregate function name
   * @param string $column Column to aggregate
   * @param string $alias Alias for the result
   * @return mixed Aggregate result or null
   */
  protected function aggregate(string $fn, string $column, string $alias = 'aggregate')
  {
    $this->applyGlobalScopes();
    $this->applyTenantScope();
    $this->applyTrashableConditions();

    $query = clone $this;
    $query->select      = [];
    $query->rawSelects  = [];
    $query->orders      = [];
    $query->groups      = [];
    $query->havings     = [];
    $query->limit       = null;
    $query->offset      = null;

    $query->includes    = [];


    $col = $query->wrapColumn($column);
    $query->selectRaw(strtoupper($fn) . "({$col}) AS {$alias}");

    $res = $query->get()->first();

    return is_numeric($res->{$alias} ?? null)
      ? $res->{$alias} + 0
      : 0;
  }

  /**
   * Wrap a column name for use in SQL queries
   *
   * @param string $col Column name
   * @return string Wrapped column name
   */
  protected function wrapColumn(string $col): string
  {
    return $col === '*' ? '*' : "`{$col}`";
  }

  /**
   * Calculate the sum of a column's values
   *
   * @param string $column Column to sum
   * @return float Sum value or 0.0
   *
   * @example sum('revenue') // Total revenue
   * @example where('status', 'completed')->sum('amount')
   */
  public function sum(string $column)
  {
    return $this->aggregate('sum', $column);
  }

  /**
   * Calculate the average value of a column
   *
   * @param string $column Column to evaluate
   * @return float Average value or 0.0
   *
   * @example avg('rating') // Get average product rating
   * @example where('year', 2023)->avg('test_score')
   */
  public function avg(string $column)
  {
    return $this->aggregate('avg', $column);
  }

  /**
   * Retrieve the maximum value of a column
   *
   * @param string $column Column to evaluate
   * @return mixed Maximum value or null
   *
   * @example max('age') // Returns highest age
   * @example where('department', 'IT')->max('salary')
   */
  public function max(string $column)
  {
    return $this->aggregate('max', $column);
  }

  /**
   * Retrieve the minimum value of a column
   *
   * @param string $column Column to evaluate
   * @return mixed Minimum value or null
   *
   * @example min('price') // Find lowest price
   * @example where('in_stock', true)->min('discounted_price')
   */
  public function min(string $column)
  {
    return $this->aggregate('min', $column);
  }

  /**
   * Check if the query has any results
   *
   * @return bool True if query has results, false otherwise
   */
  public function exists(): bool
  {
    return $this->count() > 0;
  }

  /**
   * Check if the query has no results
   *
   * @return bool True if query has no results, false otherwise
   */
  public function doesNotExists(): bool
  {
    return !$this->exists();
  }



  /**
   * Process records in chunks to avoid memory overload.
   *
   * @param int $chunkSize
   * @param callable $callback Receives (DataSet $chunk, int $page)
   * @return void
   */
  public function chunk(int $chunkSize, callable $callback): void
  {
    $page = 0;
    do {
      $offset = $page * $chunkSize;
      $clone = clone $this;
      $results = $clone->limit($chunkSize)->offset($offset)->get();
      if (count($results->all()) === 0) {
        break;
      }
      $continue = $callback($results, $page);
      $page++;
    } while ($continue !== false);
  }

  /**
   * Stream results as a generator, yielding entities one at a time.
   *
   * This method can be used to process large datasets in chunks, avoiding memory overload.
   * It will yield one entity at a time, allowing you to process large datasets in a memory efficient way.
   *
   * @param int $batchSize Number of records to fetch per batch (default: 1000)
   * @param array $options Optional options for the query (e.g. ['orderBy' => 'id', 'direction' => 'asc', 'limit' => 1000])
   * @return \Generator
   *
   * @example
   *
   * foreach (QueryEngine::for(User::class)->where('active', '=', 1)->streamEach(50000, ['orderBy' => 'id', 'direction' => 'asc', 'limit' => 1000]) as $user) {
   *    $user->doSomething();
   * }
   *
   */
  public function streamEach(int $batchSize = 1000, array $options = []): \Generator
  {
    $page = 0;
    do {
      $offset = $page * $batchSize;
      $clone = clone $this;
      if (isset($options['orderBy'])) {
        $clone->orderBy($options['orderBy']);
      }
      $results = $clone->limit($batchSize)->offset($offset)->get();
      $entities = $results->all();
      if (!$entities) break;
      foreach ($entities as $entity) {
        yield $entity;
      }
      $page++;
    } while (true);
  }




  /**
   * Convert DataSet to array
   *
   * @return array
   */
  public function toArray(): array
  {
    return $this->get()->all();
  }


  /*
   |------------------------------------------------
   | CRUD OPERATIONS
   |***********************************************
   | Add CRUD operations to the query engine
   |----------------------------------------------
   */

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
   * Insert a row into the table or ignore if it already exists (MySQL/Postgres).
   *
   * @param array $data
   * @param string|array|null $conflictColumns (Postgres only) Unique column(s) for ON CONFLICT
   * @return int
   */
  public function insertOrIgnore(array $data, string|array|null $conflictColumns = null): int
  {
    if (empty($data)) return 0;

    $conn = Connection::getInstance();
    $driver = $conn->getAttribute(\PDO::ATTR_DRIVER_NAME);

    $columns = implode(', ', array_map(fn($col) => $driver === 'pgsql' ? "\"$col\"" : "`$col`", array_keys($data)));
    $placeholders = implode(', ', array_fill(0, count($data), '?'));

    if ($driver === 'mysql') {
      $sql = "INSERT IGNORE INTO `{$this->table}` ($columns) VALUES ($placeholders)";
    } elseif ($driver === 'pgsql') {
      if (!$conflictColumns) {
        throw new \InvalidArgumentException('Postgres requires conflictColumns for insertOrIgnore.');
      }
      $conflict = is_array($conflictColumns) ? implode(', ', $conflictColumns) : $conflictColumns;
      $sql = "INSERT INTO \"{$this->table}\" ($columns) VALUES ($placeholders) ON CONFLICT ($conflict) DO NOTHING";
    } else {
      throw new \RuntimeException("insertOrIgnore not supported for driver: $driver");
    }

    $this->executeStatement($sql, array_values($data));
    return (int) $conn->lastInsertId();
  }

  /**
   * Insert a row into the table or replace if it already exists (MySQL/Postgres).
   *
   * @param array $data
   * @param string|array|null $conflictColumns (Postgres only) Unique column(s) for ON CONFLICT
   * @return int
   */
  public function insertOrReplace(array $data, string|array|null $conflictColumns = null): int
  {
    if (empty($data)) return 0;

    $conn = Connection::getInstance();
    $driver = $conn->getAttribute(\PDO::ATTR_DRIVER_NAME);

    $columns = implode(', ', array_map(fn($col) => $driver === 'pgsql' ? "\"$col\"" : "`$col`", array_keys($data)));
    $placeholders = implode(', ', array_fill(0, count($data), '?'));

    if ($driver === 'mysql') {
      $sql = "REPLACE INTO `{$this->table}` ($columns) VALUES ($placeholders)";
    } elseif ($driver === 'pgsql') {
      if (!$conflictColumns) {
        throw new \InvalidArgumentException('Postgres requires conflictColumns for insertOrReplace.');
      }
      $conflict = is_array($conflictColumns) ? implode(', ', $conflictColumns) : $conflictColumns;
      $updates = implode(', ', array_map(fn($col) => "\"$col\" = EXCLUDED.\"$col\"", array_keys($data)));
      $sql = "INSERT INTO \"{$this->table}\" ($columns) VALUES ($placeholders) ON CONFLICT ($conflict) DO UPDATE SET $updates";
    } else {
      throw new \RuntimeException("insertOrReplace not supported for driver: $driver");
    }

    $this->executeStatement($sql, array_values($data));
    return (int) $conn->lastInsertId();
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
   * Update a record if it exists, or insert it if it does not.
   *
   * @param array $data Data to insert/update
   * @param string|array $uniqueColumns Unique key column(s) for matching (e.g. 'id' or ['name'])
   * @return int Number of affected rows (1 for insert, or affected rows for update)
   *
   * @example ->updateOrInsert(['name' => 'Science', ...], 'name')
   */
  public function updateOrInsert(array $data, string|array $uniqueColumns): int
  {
    if (empty($data) || empty($uniqueColumns)) return 0;

    // Prepare WHERE clause for unique columns
    $uniqueColumns = (array) $uniqueColumns;
    $where = implode(' AND ', array_map(fn($col) => "`$col` = ?", $uniqueColumns));
    $whereBindings = array_map(fn($col) => $data[$col], $uniqueColumns);

    // Prepare SET clause for update
    $updateColumns = array_diff(array_keys($data), $uniqueColumns);
    $set = implode(', ', array_map(fn($col) => "`$col` = ?", $updateColumns));
    $updateBindings = array_map(fn($col) => $data[$col], $updateColumns);

    // Try to update first
    $sql = "UPDATE `{$this->table}` SET $set WHERE $where";
    $stmt = $this->executeStatement($sql, array_merge($updateBindings, $whereBindings));
    $affected = $stmt->rowCount();

    if ($affected > 0) {
      return $affected;
    }

    // If not updated, insert
    return $this->insert($data);
  }

  /**
   * Update multiple records in the table.
   *
   * @param array $rows Array of associative arrays (each must include unique key, e.g. 'id')
   * @param string $uniqueColumn The unique key column (default: 'id')
   * @return int Total number of rows updated
   *
   * @example ->updateBatch([['id'=>1,...], ['id'=>2,...]])
   */
  public function updateBatch(array $rows, string $uniqueColumn = 'id'): int
  {
    if (empty($rows)) return 0;
    $total = 0;
    foreach ($rows as $row) {
      if (!isset($row[$uniqueColumn])) continue;
      $data = $row;
      unset($data[$uniqueColumn]);
      $set = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($data)));
      $sql = "UPDATE `{$this->table}` SET $set WHERE `$uniqueColumn` = ?";
      $bindings = array_merge(array_values($data), [$row[$uniqueColumn]]);
      $stmt = $this->executeStatement($sql, $bindings);
      $total += $stmt->rowCount();
    }
    return $total;
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



  /**
   * Paginate query results
   *
   * @param int $perPage Number of items per page
   * @param int|null $currentPage Current page number
   * @param string $pageParam URL query parameter for pagination
   * @return Paginator Pagination instance
   *
   * @example ->paginate(15) // 15 items per page
   * @example ->paginate(25, 3) // 25 items, jump to page 3
   */
  public function paginate(
    int    $perPage     = 15,
    ?int   $currentPage = null,
    string $pageParam   = 'page'
  ): Paginator {
    // 1) Figure out the page
    $currentPage = $currentPage ?? (int) ($_GET[$pageParam] ?? 1);

    // 2) If no ORDER BY was added upstream, default to id ASC
    if (empty($this->orders)) {
      $this->orderBy('id', 'asc');
    }

    // 3) Clone & count
    $totalQuery = clone $this;

    $total      = $totalQuery->count();
    $lastPage   = max((int) ceil($total / $perPage), 1);
    $offset     = ($currentPage - 1) * $perPage;

    // 4) Fetch the slice
    $dataSet = $this->limit($perPage)
      ->offset($offset)
      ->get();

    return new Paginator(
      $dataSet->all(),
      $currentPage,
      $lastPage,
      $total,
      $perPage,
      $pageParam
    );
  }

  /**
   * Paginate using cursor‐based navigation.
   *
   * Adds a `direction` query-string so we can tell NEXT (desc) vs PREV (asc).
   *
   * @param  int         $perPage      Number of items per page
   * @param  string|null $cursor       Encoded “last seen” cursor value
   * @param  string      $cursorColumn Column used for the cursor (default: 'id')
   * @return CursorPagination
   */
  public function cursorPaginate(
    int     $perPage      = 15,
    ?string $cursor       = null,
    string  $cursorColumn = 'id'
  ): CursorPaginator {
    // 0) Pull from GET if not passed
    $cursor    = $cursor ?? ($_GET['cursor'] ?? null);

    $direction = strtolower($_GET['direction'] ?? 'desc');
    // dd($direction);
    if ($direction !== 'asc') {
      $direction = 'desc';
    }

    // 1) Clone builder so we don’t taint original
    $builder = clone $this;
    $builder->orders = [];
    $builder->limit  = null;
    $builder->offset = null;

    // 2) Order by cursorColumn + direction
    $builder->orderBy($cursorColumn, $direction);

    // 3) If a cursor exists, add WHERE
    if ($cursor !== null) {
      $op = $direction === 'asc' ? '>' : '<';
      $builder->where($cursorColumn, $op, $cursor);
    }

    // 4) Grab perPage+1 to detect “has more”
    $items = $builder
      ->limit($perPage + 1)
      ->get()
      ->all();

    $hasMore = count($items) > $perPage;
    if ($hasMore) {
      array_pop($items);
    }

    // 5) If going **backwards** (asc), reverse, so it displays properly
    if ($direction === 'asc') {
      $items = array_reverse($items);
    }

    // 6) Compute cursors for the two buttons
    if ($direction === 'desc') {
      // NEXT: last item’s key; PREV: incoming cursor
      $nextCursor = $hasMore
        ? $this->getCursorValue(end($items), $cursorColumn)
        : null;
      $prevCursor = $cursor;
    } else {
      // PREV-clicked: NEXT should return to the page we came from
      //          so echo the incoming cursor;
      //    PREV cursor now is the first item’s key
      $nextCursor = $cursor;
      $prevCursor = $hasMore
        ? $this->getCursorValue(reset($items), $cursorColumn)
        : null;
    }

    return new CursorPaginator(
      $items,
      $nextCursor,
      $prevCursor,
      $perPage
    );
  }

  /**
   * Extract cursor value from a record
   */
  protected function getCursorValue($record, string $cursorColumn): string
  {
    // Handle arrays (raw database results)
    if (is_array($record)) {
      if (!isset($record[$cursorColumn])) {
        throw new \RuntimeException("Cursor column {$cursorColumn} not found in results");
      }
      return (string) $record[$cursorColumn];
    }

    // Handle Entity objects
    if (is_object($record) && method_exists($record, 'getAttribute')) {
      $value = $record->getAttribute($cursorColumn);
      if ($value === null) {
        throw new \RuntimeException("Cursor column {$cursorColumn} not found in entity");
      }
      return (string) $value;
    }

    // Fallback for objects with public properties
    if (is_object($record) && isset($record->{$cursorColumn})) {
      return (string) $record->{$cursorColumn};
    }

    throw new \RuntimeException("Cursor column {$cursorColumn} not accessible in record");
  }

  /**
   * Simple pagination with previous/next links only
   *
   * @param int $perPage Number of items per page
   * @param int|null $currentPage Current page number
   * @param string $pageParam URL query parameter name
   * @return Pagination
   */
  public function simplePaginate(
    int $perPage = 15,
    ?int $currentPage = null,
    string $pageParam = 'page'
  ): Paginator {
    $pagination = $this->paginate($perPage, $currentPage, $pageParam);
    return $pagination;
  }

  /**
   * Apply global scopes
   *
   * Apply global scopes to the query.
   * This method applies the global scopes defined in the entity class.
   *
   * @return void
   *
   * @example $this->applyGlobalScopes()
   */
  protected function applyGlobalScopes(): void
  {
    if (!$this->entityClass) return;
    $scopes = $this->entityClass::getGlobalScopes();
    foreach ($scopes as $name => $scope) {
      if (!isset($this->disabledGlobalScopes[$name])) {
        $scope($this);
      }
    }
  }

  /**
   * Remove a global scope
   *
   * Remove a global scope.
   * This method removes a global scope from the current query.
   *
   * @param string $scopeName The name of the global scope
   *
   * @return self
   *
   * @example $this->removeGlobalScope('excludeTrash')
   */
  public function removeGlobalScope(string $scopeName): self
  {
    $this->disabledGlobalScopes[$scopeName] = true;
    return $this;
  }

  /**
   * Get the disabled global scopes
   *
   * Get the disabled global scopes.
   * This method returns the disabled global scopes for the current query.
   *
   * @return array The disabled global scopes
   *
   * @example $this->getDisabledGlobalScopes()
   */
  public function getDisabledGlobalScopes(): array
  {
    return $this->disabledGlobalScopes ?? [];
  }

  /**
   * Apply trashable conditions
   *
   * Apply trashable conditions to the query based on the entity class.
   * If the entity class is not set, or the table does not have a trash column,
   * then the method does nothing. If the query is set to only include trashed
   * records, then the method adds a WHERE condition to the query where the
   * trash column is not null. If the query is set to include trashed records,
   * then the method adds a WHERE condition to the query where the trash column
   * is null.
   * @return void
   */
  protected function applyTrashableConditions(): void
  {
    // 1) If there’s no entity or it doesn’t use Trashable, skip
    if (
      ! $this->entityClass
      || ! in_array(Trashable::class, class_uses($this->entityClass), true)
    ) {
      return;
    }

    // 2) Pull column & restore‐value from the entity
    $col     = ($this->entityClass)::getTrashColumn();
    $restore = ($this->entityClass)::getRestoreValue();

    // 3) If someone already added a where on this column, do nothing
    if ($this->hasAnyTrashCondition($col)) {
      return;
    }

    // 4) onlyTrashed(): show records where the trash column ≠ restore
    if (! empty($this->onlyTrashed)) {
      if ($restore !== null) {
        $this->where($col, '!=', $restore);
      } else {
        $this->whereNotNull($col);
      }
    }
    // 5) withTrashed(): show everything
    elseif (! empty($this->withTrashed)) {
      // no filter
    }
    // 6) default: exclude trashed—i.e. trash-column = restore (or IS NULL)
    else {
      if ($restore !== null) {
        $this->where($col, '=', $restore);
      } else {
        $this->whereNull($col);
      }
    }
  }

  protected function hasAnyTrashCondition(string $column): bool
  {
    foreach ($this->wheres as $where) {
      if (($where['column'] ?? null) === $column) {
        return true;
      }
    }
    return false;
  }

  protected function hasDirectNullCondition(string $column): bool
  {
    foreach ($this->wheres as $where) {
      if (($where['type'] === 'null' && $where['column'] === $column) ||
        ($where['type'] === 'basic' && $where['operator'] === '=' && $where['value'] === null)
      ) {
        return true;
      }
    }
    return false;
  }


  protected function hasEquivalentNullCondition(string $column): bool
  {
    foreach ($this->wheres as $where) {
      if (
        $where['type'] === 'basic' &&
        $where['column'] === $column &&
        ($where['operator'] === 'IS' || $where['operator'] === 'IS NOT') &&
        ($where['value'] === null || $where['value'] === 'NULL')
      ) {
        return true;
      }
    }
    return false;
  }

  /**
   * Check if the query has a WHERE condition for a column with a specific value
   *
   * @param string $column The column name
   * @param mixed $value The value to check for
   * @return bool True if the query has a WHERE condition for the column with the specified value, false otherwise
   *
   * @example ->hasWhereCondition('id', 1)
   */
  protected function hasWhereCondition($column, $value)
  {
    foreach ($this->wheres as $where) {
      if ($where['column'] === $column && $where['value'] === $value) {
        return true;
      }
    }
    return false;
  }

  /**
   * Check if the query has a WHERE condition for a column with a null value
   *
   * @param string $column The column name
   * @return bool True if the query has a WHERE condition for the column with a null value, false otherwise
   */
  protected function hasWhereNullCondition($column)
  {
    foreach ($this->wheres as $where) {
      if ($where['column'] === $column && $where['type'] === 'Null') {
        return true;
      }
    }
    return false;
  }

  /**
   * Get the trash column name from the entity class
   *
   * @return ?string The trash column name or null if not found
   */
  private function getEntityTrashColumn(): ?string
  {
    if (!$this->entityClass) return null;
    $traits = class_uses($this->entityClass);
    if (in_array(\Pocketframe\PocketORM\Concerns\Trashable::class, $traits)) {
      return $this->entityClass::getTrashColumn();
    }
    return null;
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

    $sql = "SELECT";
    if ($this->distinct) {
      $sql .= " DISTINCT";
    }
    $sql .= " " . implode(', ', $selectColumns) . " FROM `{$this->table}`";

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

    $sql .= $this->compileOrders();

    if ($this->limit !== null) {
      $sql .= " LIMIT {$this->limit}";

      if (isset($this->offset) && $this->offset > 0) {
        $sql .= " OFFSET {$this->offset}";
      }
    }

    return $sql;
  }

  /**
   * Compile the ORDER BY clause
   *
   * Compile the ORDER BY clause based on the query builder's properties.
   * This method builds the SQL query string for the ORDER BY clause.
   *
   * @return string The compiled SQL query
   *
   * @example $this->compileOrders()
   */
  protected function compileOrders(): string
  {
    if (empty($this->orders)) return '';

    return ' ORDER BY ' . implode(', ', $this->orders);
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

    $clauses = [];
    foreach ($this->wheres as $index => $where) {
      $clause = $index === 0 ? '' : $where['boolean'] . ' ';
      $column = $this->quoteColumn($where['column'] ?? '');

      switch ($where['type']) {
        // Basic comparison
        case 'basic':
          $clause .= "{$column} {$where['operator']} ?";
          break;

        // IN/NOT IN
        case 'in':
          $placeholders = implode(', ', array_fill(0, count($where['values']), '?'));
          $not = $where['not'] ? 'NOT ' : '';
          $clause .= "{$column} {$not}IN ({$placeholders})";
          break;

        // NULL/NOT NULL
        case 'null':
          $not = $where['not'] ? 'NOT ' : '';
          $clause .= "{$column} IS {$not}NULL";
          break;

        // Column comparison
        case 'column':
          $first = $this->quoteColumn($where['first']);
          $second = $this->quoteColumn($where['second']);
          $clause .= "{$first} {$where['operator']} {$second}";
          break;

        // JSON operations
        case 'json_contains':
          $clause .= "JSON_CONTAINS({$column}, ?)";
          break;
        case 'json_contains_array':
          $placeholders = implode(', ', array_fill(0, count($where['values']), '?'));
          $clause .= "JSON_OVERLAPS({$column}, JSON_ARRAY({$placeholders}))";
          break;
        case 'json_any':
          $clause .= "JSON_SEARCH({$column}, 'one', ?) IS NOT NULL";
          break;
        case 'json_all':
          $clause .= "JSON_CONTAINS({$column}, ?) = JSON_LENGTH({$column})";
          break;
        case 'json_none':
          $clause .= "JSON_SEARCH({$column}, 'one', ?) IS NULL";
          break;

        // BETWEEN
        case 'between':
          $not = $where['not'] ? 'NOT ' : '';
          $clause .= "{$column} {$not}BETWEEN ? AND ?";
          break;
        case 'between_columns':
          $not = $where['not'] ? 'NOT ' : '';
          $start = $this->quoteColumn($where['start']);
          $end = $this->quoteColumn($where['end']);
          $clause .= "{$column} {$not}BETWEEN {$start} AND {$end}";
          break;

        // Date/time functions
        case 'date':
        case 'month':
        case 'day':
        case 'year':
        case 'time':
          $function = strtoupper($where['type']);
          $clause .= "{$function}({$column}) {$where['operator']} ?";
          break;

        // Nested queries
        case 'nested':
          $nestedSql = $where['query']->compileWheres();
          $nestedClause = substr($nestedSql, 7); // Remove ' WHERE '
          $not = $where['not'] ?? false ? 'NOT ' : '';
          $clause .= "{$not}({$nestedClause})";
          break;

        // Full-text search
        case 'fulltext':
          $clause .= "MATCH({$column}) AGAINST(? IN {$where['mode']} MODE)";
          break;

        // Raw expressions
        case 'raw':
          $clause .= $where['sql'];
          break;

        default:
          throw new \RuntimeException("Unsupported where type: {$where['type']}");
      }

      $clauses[] = $clause;
    }
    return ' WHERE ' . implode(' ', $clauses);
  }

  protected function quoteColumn(string $column): string
  {
    if (strpos($column, '.') !== false) {
      return implode('.', array_map(
        fn($part) => "`" . str_replace("`", "``", $part) . "`",
        explode('.', $column)
      ));
    }
    return "`" . str_replace("`", "``", $column) . "`";
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
    // Log the query
    $this->logSql($sql, $this->bindings);

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
    $this->applyGlobalScopes();
    $this->applyTrashableConditions();

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
   * Get the SQL query string with bindings
   *
   * Get the SQL query string with bindings.
   * This method builds the SQL query string for the current query.
   *
   * @return array The SQL query string and bindings
   *
   * @example $this->toSqlWithBindings()
   */
  public function bindSql(): array
  {
    return [
      'sql' => $this->toSql(),
      'bindings' => $this->getBindings(),
    ];
  }

  /**
   * Get the SQL query string with bindings
   *
   * Get the SQL query string with bindings.
   * This method builds the SQL query string for the current query.
   *
   * @return string The SQL query string with bindings
   *
   * @example $this->fullSql()
   */
  public function fullSql(): string
  {
    $sql = $this->toSql();
    $bindings = $this->getBindings();
    $pdo = new \PDO('sqlite::memory:'); // For quoting
    foreach ($bindings as $binding) {
      $sql = preg_replace('/\?/', $pdo->quote($binding), $sql, 1);
    }
    return $sql;
  }

  /**
   * Enable or disable query logging
   *
   * Enable or disable query logging.
   * This method enables or disables query logging for the current query.
   *
   * @param bool $enable Whether to enable or disable query logging
   *
   * @example $this->enableQueryLog(true)
   */
  public static function enableQueryLog(bool $enable = true): void
  {
    self::$loggingEnabled = $enable;
  }

  /**
   * Get the query log
   *
   * Get the query log.
   * This method returns the query log for the current query.
   *
   * @return array The query log
   *
   * @example $this->getQueryLog()
   */
  public static function getQueryLog(): array
  {
    return self::$queryLog;
  }


  /**
   * Log the SQL query
   *
   * Log the SQL query.
   * This method logs the SQL query string and bindings for debugging purposes.
   *
   * @param string $sql The SQL query string
   * @param array $bindings The bindings for the query
   * @param array $context Optional context for the query
   *
   * @example $this->logSql('SELECT * FROM users', ['id' => 1])
   */
  protected function logSql($sql, $bindings = [], $context = null)
  {
    $entry = compact('sql', 'bindings');
    if ($context) {
      $entry['context'] = $context;
    }

    $entry['memory'] = memory_get_usage() . ' bytes'; // memory usage
    $entry['peak_memory'] = memory_get_peak_usage() . ' bytes'; // peak memory usage
    $entry['total_time'] = microtime(true); // total time for execution

    if (self::$loggingEnabled) {
      self::$queryLog[] = $entry;
    }
    $this->executedSql[] = $entry;
  }

  /**
   * Get the executed SQL queries
   *
   * Get the executed SQL queries.
   * This method returns the executed SQL queries for debugging purposes.
   *
   * @return array The executed SQL queries
   *
   * @example $this->getExecutedSql()
   */
  public function getExecutedSql(): array
  {
    return $this->executedSql;
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

  /**
   * Get the bindings for the query
   *
   * Get the bindings for the query.
   * This method returns the bindings for the current query.
   *
   * @return array The bindings for the query
   *
   * @example $this->getBindings()
   */
  public function getBindings(): array
  {
    return $this->bindings ?? [];
  }
}
