<?php

declare(strict_types=1);

namespace Pocketframe\Database;

use PDO;
use PDOException;
use PDOStatement;
use Pocketframe\Exceptions\Database\QueryException;
use Pocketframe\Exceptions\DatabaseException;
use Pocketframe\Http\Request\Request;
use Pocketframe\Http\Response\Response;

class Database
{
  protected $table;
  protected $connection;
  protected $statement;
  protected array $columns = ['*'];
  protected array $conditions = [];
  protected array $joins = [];
  protected ?int $limit = null;
  protected ?int $offset = null;

  /**
   * Initialize a new database connection
   *
   * Creates a new PDO connection to a MySQL database using the provided configuration.
   * Sets PDO to throw exceptions on errors. The database config array should contain:
   * - host: Database host
   * - dbname: Database name
   * - username: Database username
   * - password: Database password
   *
   * @param array $database Database configuration array
   * @throws PDOException If connection fails
   */
  public function __construct($database)
  {
    $dsn = "mysql:host={$database['host']};port={$database['port']};dbname={$database['database']};charset=utf8mb4";

    try {
      $this->connection = new PDO($dsn, $database['username'], $database['password']);
      $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
      throw new PDOException("Database connection failed: " . $e->getMessage());
    }
  }

  /**
   * Reset the query builder state
   *
   * Resets the table, columns, conditions, joins, limit, and offset properties
   * to their initial values.
   */
  protected function reset()
  {
    $this->table = null;
    $this->columns = ['*'];
    $this->conditions = [];
    $this->joins = [];
    $this->limit = null;
    $this->offset = null;
  }

  /**
   * Set the table for the query
   *
   * Resets the query builder state and sets the table name for the current query.
   *
   * @param string $table The name of the table to query
   * @return self The current Database instance
   */
  public function table(string $table): self
  {
    $this->reset();
    $this->table = $table;
    return $this;
  }

  /**
   * Select specific columns from the table
   *
   * Sets the columns to be selected from the table.
   *
   * @param array $columns The columns to select
   * @return self The current Database instance
   */
  public function select(array $columns): self
  {
    $this->columns = $columns;
    return $this;
  }

  /**
   * Add a WHERE condition to the query
   *
   * Adds a WHERE condition to the query.
   *
   * @param string $column The column to condition on
   * @param string $operator The operator to use in the condition
   * @param string|int|Request $value The value to compare against
   * @return self The current Database instance
   */
  public function where(string $column, string $operator, string|int|Request $value): self
  {
    $this->conditions[] = ['WHERE', $column, $operator, $value];
    return $this;
  }

  /**
   * Add an OR condition to the query
   *
   * Adds an OR condition to the query.
   *
   * @param string $column The column to condition on
   * @param string $operator The operator to use in the condition
   * @param string $value The value to compare against
   * @return self The current Database instance
   */
  public function orWhere(string $column, string $operator, string $value): self
  {
    $this->conditions[] = ['OR', $column, $operator, $value];
    return $this;
  }

  /**
   * Add an AND condition to the query
   *
   * Adds an AND condition to the query.
   *
   * @param string $column The column to condition on
   * @param string $operator The operator to use in the condition
   * @param string $value The value to compare against
   * @return self The current Database instance
   */
  public function andWhere(string $column, string $operator, string $value): self
  {
    $this->conditions[] = ['AND', $column, $operator, $value];
    return $this;
  }

  /**
   * Add a WHERE condition to check if a column is NULL
   *
   * Adds a WHERE condition to check if a column is NULL.
   *
   * @param string $column The column to check for NULL
   * @return self The current Database instance
   */
  public function whereNull(string $column): self
  {
    $this->conditions[] = ['IS NULL', $column];
    return $this;
  }

  /**
   * Add a WHERE condition to check if a column is not NULL
   *
   * Adds a WHERE condition to check if a column is not NULL.
   *
   * @param string $column The column to check for not NULL
   * @return self The current Database instance
   */
  public function whereNotNull(string $column): self
  {
    $this->conditions[] = ['IS NOT NULL', $column];
    return $this;
  }

  /**
   * Add a WHERE condition to check if a column is NULL and AND
   *
   * Adds a WHERE condition to check if a column is NULL and AND.
   *
   * @param string $column The column to check for NULL
   * @return self The current Database instance
   */
  public function andWhereNull(string $column): self
  {
    $this->conditions[] = ['AND IS NULL', $column];
    return $this;
  }

  /**
   * Add a WHERE condition to check if a column is NULL and OR
   *
   * Adds a WHERE condition to check if a column is NULL and OR.
   *
   * @param string $column The column to check for NULL
   * @return self The current Database instance
   */
  public function orIsNull(string $column): self
  {
    $this->conditions[] = ['OR IS NULL', $column];
    return $this;
  }

  /**
   * Add a DESC order to the query
   *
   * Adds a DESC order to the query.
   *
   * @param string $column The column to order by
   * @return self The current Database instance
   */
  public function orderByDesc(string $column): self
  {
    $this->conditions[] = ['DESC', $column];
    return $this;
  }

  /**
   * Add an ASC order to the query
   *
   * Adds an ASC order to the query.
   *
   * @param string $column The column to order by
   * @return self The current Database instance
   */
  public function orderByAsc(string $column): self
  {
    $this->conditions[] = ['ASC', $column];
    return $this;
  }

  /**
   * Add an OR condition to check if a column is not NULL
   *
   * Adds an OR condition to check if a column is not NULL.
   *
   * @param string $column The column to check for not NULL
   * @return self The current Database instance
   */
  public function orWhereNotNull(string $column): self
  {
    $this->conditions[] = ['OR IS NOT NULL', $column];
    return $this;
  }

  /**
   * Add a JOIN to the query
   *
   * If you want to join a table to the query, use this method.
   *
   * @param string $table The table to join
   * @param string $firstColumn The first column to join on
   * @param string $operator The operator to use in the join
   * @param string $secondColumn The second column to join on
   * @param string $type The type of join to use
   * @return self The current Database instance
   */
  public function join(string $table, string $firstColumn, string $operator, string $secondColumn, string $type = 'INNER'): self
  {
    $this->joins[] = ["{$type} JOIN", $table, $firstColumn, $operator, $secondColumn];
    return $this;
  }

  /**
   * Set the limit for the query
   *
   * Sets the limit for the query.
   *
   * @param int $limit The limit to set
   * @return self The current Database instance
   */
  public function limit(int $limit): self
  {
    $this->limit = $limit;
    return $this;
  }

  /**
   * Set the offset for the query
   *
   * Sets the offset for the query.
   *
   * @param int $offset The offset to set
   * @return self The current Database instance
   */
  public function offset(int $offset): self
  {
    $this->offset = $offset;
    return $this;
  }

  /**
   * Get the results of the query
   *
   * Executes the query and returns the results.
   *
   * @return array<string, mixed> The results of the query
   */
  public function get(): array
  {
    try {

      if (empty($this->table)) {
        throw new QueryException("Table not set. Call the table() method before building the query.");
      }

      // Build base SELECT query
      $query = "SELECT " . implode(', ', $this->columns) . " FROM {$this->table}";

      // Add JOIN clauses
      foreach ($this->joins as $join) {
        $query .= " {$join[0]} {$join[1]} ON {$join[2]} {$join[3]} {$join[4]}";
      }

      // Separate WHERE conditions
      $whereConditions = [];
      $orderBy = [];
      foreach ($this->conditions as $condition) {
        if ($condition[0] === 'DESC' || $condition[0] === 'ASC') {
          $orderBy[] = "{$condition[1]} {$condition[0]}";
        } else {
          $whereConditions[] = $condition;
        }
      }

      // Build WHERE clause with parameter binding
      $whereClause = [];
      $bindValues = [];
      $isFirstCondition = true;

      foreach ($whereConditions as $condition) {
        $type = array_shift($condition);
        $column = $condition[0];

        // Handle first condition differently
        if ($isFirstCondition) {
          $isFirstCondition = false;
          $prefix = '';
        } else {
          $prefix = match ($type) {
            'OR', 'OR IS NULL', 'OR IS NOT NULL' => 'OR ',
            default => 'AND '
          };
        }

        switch ($type) {
          case 'WHERE':
          case 'AND':
          case 'OR':
            $operator = $condition[1];
            $value = $condition[2];
            $whereClause[] = "{$prefix}{$column} {$operator} ?";
            $bindValues[] = $value;
            break;

          case 'IS NULL':
          case 'IS NOT NULL':
            $whereClause[] = "{$prefix}{$column} {$type}";
            break;

          case 'AND IS NULL':
          case 'OR IS NULL':
          case 'OR IS NOT NULL':
            $modifier = str_replace('AND ', '', $type);
            $whereClause[] = "{$prefix}{$column} {$modifier}";
            break;
        }
      }

      // Add WHERE clause if needed
      if (!empty($whereClause)) {
        $query .= " WHERE " . implode(' ', $whereClause);
      }

      // Add ORDER BY
      if (!empty($orderBy)) {
        $query .= " ORDER BY " . implode(', ', $orderBy);
      }

      // Add LIMIT and OFFSET
      if ($this->limit !== null) {
        $query .= " LIMIT " . (int)$this->limit;
      }
      if ($this->offset !== null) {
        $query .= " OFFSET " . (int)$this->offset;
      }

      // Prepare and execute query
      $this->statement = $this->connection->prepare($query);
      $this->statement->execute($bindValues);

      return $this->statement->fetchAll(PDO::FETCH_ASSOC);
    } catch (QueryException $e) {
      throw new QueryException($e->getMessage());
      return [];
    } finally {
      $this->reset();
    }
  }


  /**
   * Execute a raw query
   *
   * Executes a raw query and returns the results.
   *
   * @param string $query The query to execute
   * @param array $param The parameters to bind to the query
   * @return PDOStatement|array The results of the query
   */
  public function query($query, $param = []): PDOStatement|array
  {
    try {
      $this->statement = $this->connection->prepare($query);
      $this->statement->execute($param);
      return $this->statement;
    } catch (QueryException $e) {
      throw new QueryException($e->getMessage());
      return [];
    }
  }

  /**
   * Get all records from a table
   *
   * Retrieves all records from a table and returns them as an array.
   *
   * @param string|null $table The name of the table to query
   * @return array The results of the query
   */
  public function all($table = null): array
  {
    try {
      $this->statement = $this->connection->prepare("SELECT * FROM {$table}");
      $this->statement->execute();
      return $this->statement->fetchAll(PDO::FETCH_ASSOC);
    } catch (QueryException $e) {
      throw new QueryException($e->getMessage());
      return [];
    }
  }

  /**
   * Get records where the deleted_at column is not NULL
   *
   * Retrieves records where the deleted_at column is not NULL.
   *
   * @param string $table The name of the table to query
   * @return array The results of the query
   */
  public function whereNotDeleted(string $table, $column = null, $direction = null): array
  {
    if (!is_null($direction)) {
      try {
        $this->statement = $this->connection->prepare(
          "SELECT * FROM {$table}
                                WHERE deleted_at
                                IS NULL" . $this->orderBy($column, $direction)
        );
        $this->statement->execute();
        return $this->statement->fetchAll(PDO::FETCH_ASSOC);
      } catch (QueryException $e) {
        throw new QueryException($e->getMessage());
        return [];
      }
    }

    try {
      $this->statement = $this->connection->prepare(
        "SELECT * FROM {$table} WHERE deleted_at IS NULL"
      );
      $this->statement->execute();
      return $this->statement->fetchAll();
    } catch (QueryException $e) {
      throw new QueryException($e->getMessage());
      return [];
    }
  }

  /**
   * Order the query by a column
   *
   * Orders the query by a column.
   *
   * @param string|null $column The column to order by
   * @param string|null $direction The direction to order by
   * @return string The order by clause
   */
  public function orderBy($column = null, $direction = null): string
  {
    return ' ORDER BY ' . $column . ' ' . $direction;
  }

  /**
   * Get records where the deleted_at column is not NULL
   *
   * Retrieves records where the deleted_at column is not NULL.
   *
   * @param string $table The name of the table to query
   * @return array The results of the query
   */
  public function whereDeleted(string $table): array
  {
    try {
      $this->statement = $this->connection->prepare("SELECT * FROM {$table} WHERE deleted_at IS NOT NULL");
      $this->statement->execute();
      return $this->statement->fetchAll();
    } catch (QueryException $e) {
      throw new QueryException($e->getMessage());
      return [];
    }
  }

  /**
   * Get the latest records
   *
   * Retrieves the latest records from the table.
   *
   * @param string $column The column to order by
   * @return array The results of the query
   */
  public function latest(string $column = 'created_at'): array|string
  {
    try {
      $query = "SELECT " . implode(', ', $this->columns) . " FROM {$this->table}";

      if (!empty($this->joins)) {
        foreach ($this->joins as $join) {
          $query .= " {$join[0]} {$join[1]} ON {$join[2]} {$join[3]} {$join[4]}";
        }
      }

      if (count($this->conditions) > 0) {
        $query .= " WHERE ";
        foreach ($this->conditions as $condition) {
          $query .= match ($condition[0]) {
            'OR'             => " OR {$condition[1]} {$condition[2]} '{$condition[3]}'",
            'AND'            => " AND {$condition[1]} {$condition[2]} '{$condition[3]}'",
            'IS NULL'        => " {$condition[1]} IS NULL",
            'IS NOT NULL'    => " {$condition[1]} IS NOT NULL",
            'OR IS NULL'     => " OR {$condition[1]} IS NULL",
            'DESC'           => " ORDER BY {$condition[1]} DESC",
            'ASC'            => " ORDER BY {$condition[1]} ASC",
            'AND IS NULL'    => " AND {$condition[1]} IS NULL",
            'OR IS NOT NULL' => " OR {$condition[1]} IS NOT NULL",
            'WHERE'          => " {$condition[1]} {$condition[2]} '{$condition[3]}'",
          };
        }
      }

      $query .= " ORDER BY {$column} DESC";

      if (!is_null($this->limit)) {
        $query .= " LIMIT {$this->limit}";
      }

      if (!is_null($this->offset)) {
        $query .= " OFFSET {$this->offset}";
      }

      $this->statement = $this->connection->prepare($query);
      $this->statement->execute();
      return $this->statement->fetchAll(PDO::FETCH_ASSOC);
    } catch (QueryException $e) {
      throw new QueryException($e->getMessage());
      return [];
    } finally {
      $this->reset();
    }
  }

  /**
   * Get the first record from the query
   *
   * Retrieves the first record from the query.
   *
   * @return array|string The first record from the query
   */
  public function first(): array|string|Response
  {
    try {
      $query = "SELECT " . implode(', ', $this->columns) . " FROM {$this->table}";

      if (!empty($this->joins)) {
        foreach ($this->joins as $join) {
          $query .= " {$join[0]} {$join[1]} ON {$join[2]} {$join[3]} {$join[4]}";
        }
      }

      if (count($this->conditions) > 0) {
        $query .= " WHERE ";
        foreach ($this->conditions as $condition) {
          $query .= match ($condition[0]) {
            'OR'             => " OR {$condition[1]} {$condition[2]} '{$condition[3]}'",
            'AND'            => " AND {$condition[1]} {$condition[2]} '{$condition[3]}'",
            'IS NULL'        => " {$condition[1]} IS NULL",
            'IS NOT NULL'    => " {$condition[1]} IS NOT NULL",
            'OR IS NULL'     => " OR {$condition[1]} IS NULL",
            'DESC'           => " ORDER BY {$condition[1]} DESC",
            'ASC'            => " ORDER BY {$condition[1]} ASC",
            'AND IS NULL'    => " AND {$condition[1]} IS NULL",
            'OR IS NOT NULL' => " OR {$condition[1]} IS NOT NULL",
            'WHERE'          => " {$condition[1]} {$condition[2]} '{$condition[3]}'",
          };
        }
      }

      $query .= " LIMIT 1";

      $this->statement = $this->connection->prepare($query);
      $this->statement->execute();
      $result = $this->statement->fetch(PDO::FETCH_ASSOC);

      if (!$result) {
        return Response::view('errors/' . Response::NOT_FOUND);
      }
      return $result;
    } catch (QueryException $e) {
      throw new QueryException($e->getMessage());
      return [];
    } finally {
      $this->reset();
    }
  }

  /**
   * Find a record by a column and value
   *
   * Retrieves a record from the table based on the specified column and value.
   *
   * @param string $table The name of the table to query
   * @param string $column The column to search for
   * @param string $operator The operator to use in the search
   * @param int|string $value The value to search for
   * @return array The results of the query
   */
  public function find(string $table, string $column, string $operator, int|string $value): array
  {
    try {
      $this->statement = $this->connection->prepare("SELECT * FROM {$table} WHERE {$column} {$operator} :value");
      $this->statement->bindParam(':value', $value);
      $this->statement->execute();
      return $this->statement->fetch();
    } catch (QueryException $e) {
      throw new QueryException($e->getMessage());
      return [];
    }
  }

  /**
   * Find a record by a column and value or fail
   *
   * Retrieves a record from the table based on the specified column and value.
   *
   * @param string $table The name of the table to query
   * @param string $column The column to search for
   * @param string $operator The operator to use in the search
   * @param int|string $value The value to search for
   * @return array The results of the query
   */
  public function findOrFail(string $table, string $column, string $operator, int|string $value)
  {
    $result = $this->find($table, $column, $operator, $value);

    if (!$result) {
      throw new QueryException("No record found for {$column} {$operator} {$value}");
    }

    return $result;
  }

  /**
   * Count the number of rows matching the query
   *
   * Constructs and executes a COUNT query based on the current query builder state.
   *
   * @return int The number of rows matching the query
   */
  public function count(): int
  {
    try {
      // Save the current query builder state
      $table = $this->table;
      $conditions = $this->conditions;
      $joins = $this->joins;

      // Build base COUNT query
      $query = "SELECT COUNT(*) as total FROM {$this->table}";

      // Add JOIN clauses
      foreach ($this->joins as $join) {
        $query .= " {$join[0]} {$join[1]} ON {$join[2]} {$join[3]} {$join[4]}";
      }

      // Separate WHERE conditions
      $whereConditions = [];
      $bindValues = [];
      $isFirstCondition = true;

      foreach ($this->conditions as $condition) {
        if ($condition[0] === 'DESC' || $condition[0] === 'ASC') {
          // Skip ORDER BY conditions for COUNT queries
          continue;
        }

        $type = array_shift($condition);
        $column = $condition[0];

        // Handle first condition differently
        if ($isFirstCondition) {
          $isFirstCondition = false;
          $prefix = '';
        } else {
          $prefix = match ($type) {
            'OR', 'OR IS NULL', 'OR IS NOT NULL' => 'OR ',
            default => 'AND '
          };
        }

        switch ($type) {
          case 'WHERE':
          case 'AND':
          case 'OR':
            $operator = $condition[1];
            $value = $condition[2];
            $whereConditions[] = "{$prefix}{$column} {$operator} ?";
            $bindValues[] = $value;
            break;

          case 'IS NULL':
          case 'IS NOT NULL':
            $whereConditions[] = "{$prefix}{$column} {$type}";
            break;

          case 'AND IS NULL':
          case 'OR IS NULL':
          case 'OR IS NOT NULL':
            $modifier = str_replace('AND ', '', $type);
            $whereConditions[] = "{$prefix}{$column} {$modifier}";
            break;
        }
      }

      // Add WHERE clause if needed
      if (!empty($whereConditions)) {
        $query .= " WHERE " . implode(' ', $whereConditions);
      }

      // Prepare and execute query
      $this->statement = $this->connection->prepare($query);
      $this->statement->execute($bindValues);

      // Fetch the count result
      $result = $this->statement->fetch(PDO::FETCH_ASSOC);
      return (int) $result['total'];
    } catch (QueryException $e) {
      throw new QueryException($e->getMessage());
      return 0;
    } finally {
      // Restore the query builder state
      $this->table = $table;
      $this->conditions = $conditions;
      $this->joins = $joins;
    }
  }

  /**
   * Paginate the query results
   *
   * Paginates the query results based on the specified number of records per page.
   *
   * @param int $perPage The number of records per page
   * @return array The paginated results
   */
  public function paginate(int $perPage = 15): Pagination
  {

    $currentPage = $_GET['page'] ?? 1;
    $totalRecords = $this->count();
    $lastPage = (int) ceil($totalRecords / $perPage);
    $offset = ($currentPage - 1) * $perPage;

    $this->limit($perPage)->offset($offset);

    return new Pagination(
      $this->get(),
      (int) $currentPage,
      $lastPage,
      $totalRecords,
      $perPage
    );
  }

  /**
   * Insert a new record into the table
   *
   * Inserts a new record into the table.
   *
   * @param string $table The name of the table to insert into
   * @param array $columns The columns to insert into the table
   * @return array|null The inserted record
   */
  public function insert(string $table, array $columns): ?array
  {
    try {
      $column_names = implode(', ', array_keys($columns));
      $placeholders = rtrim(str_repeat('?, ', count($columns)), ', ');

      $query = "INSERT INTO {$table} ({$column_names}) VALUES ({$placeholders})";
      $this->statement = $this->connection->prepare($query);

      // Bind parameters
      $i = 1;
      foreach ($columns as $value) {
        $this->statement->bindValue($i++, $value);
      }

      $this->statement->execute();

      $last_insert_id = $this->connection->lastInsertId();

      // Fetch the inserted row using the last insert ID
      $select = "SELECT * FROM {$table} WHERE id = ?";
      $select_statement = $this->connection->prepare($select);
      $select_statement->execute([$last_insert_id]);

      // Return the fetched row
      return $select_statement->fetch(PDO::FETCH_ASSOC);
    } catch (QueryException $e) {
      error_log("Insert failed: " . $e->getMessage());
      throw new QueryException("Insert failed: " . $e->getMessage());
      return null;
    }
  }

  /**
   * Update a record in the table
   *
   * Updates a record in the table.
   *
   * @param string $table The name of the table to update
   * @param array $sets The columns to update
   * @param array $condition The condition to update the record
   * @return array|null The updated record
   */
  public function update(string $table, array $sets, array $condition)
  {
    try {
      $set_clause = '';
      foreach ($sets as $column => $value) {
        $set_clause .= "$column = ?, ";
      }
      $set_clause = rtrim($set_clause, ', ');

      $where_clause = '';
      foreach ($condition as $col => $val) {
        $where_clause .= "$col = ? AND ";
      }
      $where_clause = rtrim($where_clause, 'AND ');

      $query = "UPDATE {$table} SET {$set_clause} WHERE {$where_clause}";
      $this->statement = $this->connection->prepare($query);

      // Bind parameters for SET clause
      $i = 1;
      foreach ($sets as $value) {
        $this->statement->bindValue($i++, $value);
      }

      // Bind parameters for WHERE clause
      foreach ($condition as $value) {
        $this->statement->bindValue($i++, $value);
      }

      $this->statement->execute();
      $last_insert_id = $this->connection->lastInsertId();

      // Fetch the inserted row using the last insert ID
      $select = "SELECT * FROM {$table} WHERE id = ?";
      $select_statement = $this->connection->prepare($select);
      $select_statement->execute([$last_insert_id]);

      // Return the fetched row
      return $select_statement->fetch(PDO::FETCH_ASSOC);
    } catch (QueryException $e) {
      throw new QueryException("Update failed: " . $e->getMessage());
      return null;
    }
  }

  /**
   * Delete a record from the table
   *
   * Deletes a record from the table.
   *
   * @param string $table The name of the table to delete from
   * @param array $condition The condition to delete the record
   * @return array The results of the query
   */
  public function delete(string $table, array $condition = [])
  {
    try {
      $column = implode(', ', array_keys($condition));
      $value = implode(', ', array_values($condition));

      $this->statement = $this->connection->prepare("UPDATE {$table} SET deleted_at = CURRENT_TIMESTAMP  WHERE $column = :value");
      $this->statement->bindParam(':value', $value);
      $this->statement->execute();
    } catch (QueryException $e) {
      throw new QueryException("Failed to delete an item: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Force delete a record from the table
   *
   * Deletes a record from the table.
   *
   * @param string $table The name of the table to delete from
   * @param array $condition The condition to delete the record
   * @return array The results of the query
   */
  public function forceDelete(string $table, array $condition = [])
  {
    try {
      $column = implode(', ', array_keys($condition));
      $value = implode(', ', array_values($condition));

      $this->statement = $this->connection->prepare("DELETE FROM {$table}  WHERE $column = :value");
      $this->statement->bindParam(':value', $value);
      $this->statement->execute();
    } catch (QueryException $e) {
      throw new QueryException("Failed to delete an item: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Begin a transaction
   *
   * Begins a transaction.
   *
   * @return void
   */
  public function beginTransaction()
  {
    $this->connection->beginTransaction();
  }

  /**
   * Commit a transaction
   *
   * Commits a transaction.
   *
   * @return void
   */
  public function commit()
  {
    $this->connection->commit();
  }

  /**
   * Rollback a transaction
   *
   * Rolls back a transaction.
   *
   * @return void
   */
  public function rollback()
  {
    $this->connection->rollBack();
  }

  /**
   * Transaction a callback
   *
   * Executes a callback within a transaction.
   *
   * @param callable $callback The callback to execute
   * @return mixed The result of the callback
   */
  public function transaction(callable $callback)
  {
    try {
      $this->beginTransaction();
      $result = $callback($this);
      $this->commit();
      return $result;
    } catch (QueryException $err) {
      $this->rollback();
      throw new QueryException("Transaction failed: " . $err->getMessage());
      return null;
    }
  }
}
