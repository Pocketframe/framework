<?php

declare(strict_types=1);

namespace Core\Database;

use PDO;
use PDOException;
use PDOStatement;

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

    public function __construct($database)
    {
        $dsn = 'mysql:' . http_build_query($database, '', ';');

        try {
            $this->connection = new PDO($dsn, $database['username'], $database['password']);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            error_log("Connection failed: " . $e->getMessage());
            throw new PDOException("Connection failed: " . $e->getMessage());
        }
    }

    protected function reset()
    {
        $this->table = null;
        $this->columns = ['*'];
        $this->conditions = [];
        $this->joins = [];
        $this->limit = null;
        $this->offset = null;
    }

    public function table(string $table): self
    {
        $this->reset();
        $this->table = $table;
        return $this;
    }

    public function select(array $columns): self
    {
        $this->columns = $columns;
        return $this;
    }

    public function where(string $column, string $operator, string $value): self
    {
        $this->conditions[] = ['WHERE', $column, $operator, $value];
        return $this;
    }

    public function orWhere(string $column, string $operator, string $value): self
    {
        $this->conditions[] = ['OR', $column, $operator, $value];
        return $this;
    }

    public function andWhere(string $column, string $operator, string $value): self
    {
        $this->conditions[] = ['AND', $column, $operator, $value];
        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->conditions[] = ['IS NULL', $column];
        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $this->conditions[] = ['IS NOT NULL', $column];
        return $this;
    }


    public function andWhereNull(string $column): self
    {
        $this->conditions[] = ['AND IS NULL', $column];
        return $this;
    }

    public function orIsNull(string $column): self
    {
        $this->conditions[] = ['OR IS NULL', $column];
        return $this;
    }

    public function orderByDesc(string $column): self
    {
        $this->conditions[] = ['DESC', $column];
        return $this;
    }

    public function orderByAsc(string $column): self
    {
        $this->conditions[] = ['ASC', $column];
        return $this;
    }

    public function orWhereNotNull(string $column): self
    {
        $this->conditions[] = ['OR IS NOT NULL', $column];
        return $this;
    }

    public function join(string $table, string $firstColumn, string $operator, string $secondColumn, string $type = 'INNER'): self
    {
        $this->joins[] = ["{$type} JOIN", $table, $firstColumn, $operator, $secondColumn];
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    public function get(): array|string
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

            if (!is_null($this->limit)) {
                $query .= " LIMIT {$this->limit}";
            }

            if (!is_null($this->offset)) {
                $query .= " OFFSET {$this->offset}";
            }


            // return $query;
            $this->statement = $this->connection->prepare($query);
            $this->statement->execute();
            return $this->statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Query failed: " . $e->getMessage());
            return [];
        } finally {
            $this->reset();
        }
    }

    public function query($query, $param = []): PDOStatement|array
    {
        try {
            $this->statement = $this->connection->prepare($query);
            $this->statement->execute($param);
            return $this->statement;
        } catch (PDOException $e) {
            return [];
            error_log("Query failed: " . $e->getMessage());
            return [];
        }
    }


    public function all($table = null): array
    {
        try {
            $this->statement = $this->connection->prepare("SELECT * FROM {$table}");
            $this->statement->execute();
            return $this->statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Query failed: " . $e->getMessage());
            return [];
        }
    }

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
            } catch (PDOException $e) {
                error_log("Query failed: " . $e->getMessage());
                return [];
            }
        }

        try {
            $this->statement = $this->connection->prepare(
                "SELECT * FROM {$table} WHERE deleted_at IS NULL"
            );
            $this->statement->execute();
            return $this->statement->fetchAll();
        } catch (PDOException $e) {
            error_log("Query failed: " . $e->getMessage());
            return [];
        }
    }

    public function orderBy($column = null, $direction = null): string
    {
        return ' ORDER BY ' . $column . ' ' . $direction;
    }


    public function whereDeleted(string $table): array
    {
        try {
            $this->statement = $this->connection->prepare("SELECT * FROM {$table} WHERE deleted_at IS NOT NULL");
            $this->statement->execute();
            return $this->statement->fetchAll();
        } catch (PDOException $e) {
            error_log("Query failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * This function is used to find a single record based on a column and value
     *
     * @param string $table
     * @param string $column
     * @param string $operator
     * @param int|string $value
     * @return array<string, mixed>
     * @link https://github.com/williamug
     */
    public function find(string $table, string $column, string $operator, int|string $value): array
    {
        try {
            $this->statement = $this->connection->prepare("SELECT * FROM {$table} WHERE {$column} {$operator} :value");
            $this->statement->bindParam(':value', $value);
            $this->statement->execute();
            return $this->statement->fetch();
        } catch (PDOException $e) {
            error_log("Query failed: " . $e->getMessage());
            return [];
        }
    }

    public function findOrFail(string $table, string $column, string $operator, int|string $value)
    {
        $result = $this->find($table, $column, $operator, $value);

        if (!$result) {
            throw new PDOException("No record found for {$column} {$operator} {$value}");
        }

        return $result;
    }


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
        } catch (PDOException $e) {
            error_log("Insert failed: " . $e->getMessage());
            return null;
        }
    }

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
        } catch (PDOException $e) {
            error_log("Update failed: " . $e->getMessage());
            return null;
        }
    }

    public function delete(string $table, array $condition = [])
    {
        try {
            $column = implode(', ', array_keys($condition));
            $value = implode(', ', array_values($condition));

            $this->statement = $this->connection->prepare("UPDATE {$table} SET deleted_at = CURRENT_TIMESTAMP  WHERE $column = :value");
            $this->statement->bindParam(':value', $value);
            $this->statement->execute();
        } catch (PDOException $e) {
            error_log("Failed to delete an item: " . $e->getMessage());
            return [];
        }
    }

    public function forceDelete(string $table, array $condition = [])
    {
        try {
            $column = implode(', ', array_keys($condition));
            $value = implode(', ', array_values($condition));

            $this->statement = $this->connection->prepare("DELETE FROM {$table}  WHERE $column = :value");
            $this->statement->bindParam(':value', $value);
            $this->statement->execute();
        } catch (PDOException $e) {
            error_log("Failed to delete an item: " . $e->getMessage());
            return [];
        }
    }


    public function beginTransaction()
    {
        $this->connection->beginTransaction();
    }

    public function commit()
    {
        $this->connection->commit();
    }

    public function rollback()
    {
        $this->connection->rollBack();
    }

    public function transaction(callable $callback)
    {
        try {
            $this->beginTransaction();
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (PDOException $err) {
            $this->rollback();
            error_log("Transaction failed: " . $err->getMessage());
            return null;
        }
    }
}
