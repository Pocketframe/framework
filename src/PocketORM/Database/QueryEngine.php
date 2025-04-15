<?php

namespace Pocketframe\PocketORM\Database;

use Closure;
use PDO;
use PDOException;
use Pocketframe\Database\CursorPagination;
use Pocketframe\Database\Pagination;
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
  protected ?int $offset         = null;
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
    // $this->select = [];
    $this->rawSelects[] = $expression;
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
   * Add a WHERE JSON_CONTAINS condition
   *
   * Add a WHERE JSON_CONTAINS condition to the query.
   *
   * @param string $column Column name
   * @param mixed $value Value to compare
   * @param string $boolean Logical operator ('AND' or 'OR')
   * @return self
   *
   * @example ->whereAny('tags', '["sale","clearance"]')
   * @example ->orWhereAny('categories', 'electronics')
   */
  public function whereAny(string $column, $value, string $boolean = 'AND'): self
  {
    $this->wheres[] = [
      'type' => 'json_any',
      'column' => $column,
      'value' => $value,
      'boolean' => $boolean,
    ];
    $this->bindings[] = $value;
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
   * @example ->orWhereAny('tags', 'tag1')
   */
  public function orWhereAny(string $column, $value): self
  {
    return $this->whereAny($column, $value, 'OR');
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
   * @example ->whereAll('permissions', '["read","write"]')
   * @example ->orWhereAll('roles', '["admin"]')
   */
  public function whereAll(string $column, $value, string $boolean = 'AND'): self
  {
    $this->wheres[] = [
      'type' => 'json_all',
      'column' => $column,
      'value' => $value,
      'boolean' => $boolean,
    ];
    $this->bindings[] = $value;
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
   * @example ->orWhereAll('tags', 'tag1')
   */
  public function orWhereAll(string $column, $value): self
  {
    return $this->whereAll($column, $value, 'OR');
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
   * @example ->whereNone('tags', 'obsolete')
   * @example ->orWhereNone('categories', 'discontinued')
   */
  public function whereNone(string $column, $value, string $boolean = 'AND'): self
  {
    $this->wheres[] = [
      'type' => 'json_none',
      'column' => $column,
      'value' => $value,
      'boolean' => $boolean,
    ];
    $this->bindings[] = $value;
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
   * @example ->orWhereNone('tags', 'tag1')
   */
  public function orWhereNone(string $column, $value): self
  {
    return $this->whereNone($column, $value, 'OR');
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
    $column = $column ?? 'created_at';
    return $this->orderBy($column, 'ASC');
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
   * Conditionally add a WHERE clause
   *
   * @param bool $condition Condition to check
   * @param string $column Column name
   * @param mixed $operator Operator or value
   * @param mixed|null $value Comparison value
   * @param string $boolean Logical operator
   * @return self
   *
   * @example whereIf($isAdmin, 'role', 'admin') // Only add if $isAdmin is true
   */
  public function whereIf(bool $condition, string $column, $operator, $value = null, string $boolean = 'AND'): self
  {
    if ($condition) {
      return $this->where($column, $operator, $value, $boolean);
    }
    return $this;
  }

  /**
   * Conditionally add an OR WHERE clause
   *
   * @param bool $condition Condition to check
   * @param string $column Column name
   * @param mixed $operator Operator or value
   * @param mixed|null $value Comparison value
   * @return self
   */
  public function orWhereIf(bool $condition, string $column, $operator, $value = null): self
  {
    if ($condition) {
      return $this->orWhere($column, $operator, $value);
    }
    return $this;
  }

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
    $query = clone $this;
    $query->select = ["COUNT({$column}) AS aggregate"];
    $query->limit = null;
    $query->offset = null;
    $result = $query->get()->first();
    return (int) ($result->aggregate ?? 0);
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
    $query = clone $this;
    $query->select = ["MAX({$column}) AS aggregate"];
    $query->limit = null;
    $query->offset = null;
    $result = $query->get()->first();
    return $result->aggregate ?? null;
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
    $query = clone $this;
    $query->select = ["MIN({$column}) AS aggregate"];
    $query->limit = null;
    $query->offset = null;
    $result = $query->get()->first();
    return $result->aggregate ?? null;
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
  public function avg(string $column): float
  {
    $query = clone $this;
    $query->select = ["AVG({$column}) AS aggregate"];
    $query->limit = null;
    $query->offset = null;
    $result = $query->get()->first();
    return (float) ($result->aggregate ?? 0.0);
  }

  /**
   * Calculate the sum of a column's values
   *
   * @param string $column Column to sum
   * @return mixed Sum value or 0
   *
   * @example sum('revenue') // Total revenue
   * @example where('status', 'completed')->sum('amount')
   */
  public function sum(string $column)
  {
    $query = clone $this;
    $query->select = ["SUM({$column}) AS aggregate"];
    $query->limit = null;
    $query->offset = null;
    $result = $query->get()->first();
    return is_numeric($result->aggregate ?? 0) ? $result->aggregate + 0 : 0;
  }


  /**
   * Paginate query results
   *
   * @param int $perPage Number of items per page
   * @param int|null $currentPage Current page number
   * @param string $pageParam URL query parameter for pagination
   * @return Pagination Pagination instance
   *
   * @example ->paginate(15) // 15 items per page
   * @example ->paginate(25, 3) // 25 items, jump to page 3
   */
  public function paginate(
    int $perPage = 15,
    ?int $currentPage = null,
    string $pageParam = 'page'
  ): Pagination {
    // Get current page from request if not provided
    $currentPage = $currentPage ?? ($_GET[$pageParam] ?? 1);

    // Clone query to preserve original state
    $totalQuery = clone $this;

    // Get total records count
    $total = $totalQuery->count();

    // Calculate pagination values
    $lastPage = max((int) ceil($total / $perPage), 1);
    $offset = ($currentPage - 1) * $perPage;

    // Get paginated data
    $data = $this->limit($perPage)
      ->offset($offset)
      ->get()
      ->toArray();

    return new Pagination(
      $data,
      (int) $currentPage,
      $lastPage,
      $total,
      $perPage
    );
  }

  /**
   * Paginate using cursor-based navigation
   *
   * @param int $perPage Number of items per page
   * @param string|null $cursor Current cursor value
   * @param string $cursorColumn Column to use for cursors (default: 'id')
   * @param string $direction Order direction ('asc' or 'desc')
   * @return CursorPagination
   *
   * @example ->cursorPaginate(15, 'last_seen_id')
   * @example ->cursorPaginate(25, 'Zm9vYmFy', 'uuid', 'desc')
   */
  public function cursorPaginate(
    int $perPage = 15,
    ?string $cursor = null,
    string $cursorColumn = 'id',
    string $direction = 'desc'
  ): CursorPagination {
    // Store original query state
    $originalOrders = $this->orders;
    $originalLimit = $this->limit;

    try {
      // Always order by cursor column
      $this->orders = ["$cursorColumn $direction"];

      // Add cursor condition
      if ($cursor !== null) {
        $operator = $direction === 'asc' ? '>' : '<';
        $this->where($cursorColumn, $operator, $cursor);
      }

      // Get one extra record to check for next page
      $this->limit = $perPage + 1;

      $data = $this->get()->toArray();

      // Check if there's more data
      $hasMore = count($data) > $perPage;
      if ($hasMore) {
        array_pop($data); // Remove extra record
      }

      // Determine cursors
      $nextCursor = $hasMore ? $this->getCursorValue(end($data), $cursorColumn) : null;
      $prevCursor = $cursor;

      return new CursorPagination(
        $data,
        $nextCursor,
        $prevCursor,
        $perPage
      );
    } finally {
      // Restore original query state
      $this->orders = $originalOrders;
      $this->limit = $originalLimit;
    }
  }

  /**
   * Extract cursor value from a record
   */
  protected function getCursorValue(array $record, string $cursorColumn): string
  {
    if (!isset($record[$cursorColumn])) {
      throw new \RuntimeException("Cursor column {$cursorColumn} not found in results");
    }

    return (string) $record[$cursorColumn];
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
  ): Pagination {
    $pagination = $this->paginate($perPage, $currentPage, $pageParam);
    return $pagination;
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

    $sql .= $this->compileOrders();

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
    return $this->bindings;
  }
}
