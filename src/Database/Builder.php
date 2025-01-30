<?php

namespace Core\Database;

use PDO;
use PDOException;

class Builder extends Database
{
    protected $table;
    protected $statement;
    protected array $conditions = [];

    public function table(string $table): self
    {
        $this->table = $table;
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

    public function andWhere(string $column, string $operator, string $value)
    {
        $this->conditions[] = ['AND', $column, $operator, $value];
        return $this;
    }

    public function whereNull(string $column)
    {
        $this->conditions[] = ['IS NULL', $column];
        return $this;
    }

    public function whereNotNull(string $column)
    {
        $this->conditions[] = ['IS NOT NULL', $column];
        return $this;
    }


    public function andWhereNull(string $column)
    {
        $this->conditions[] = ['AND IS NULL', $column];
        return $this;
    }

    public function orIsNull(string $column)
    {
        $this->conditions[] = ['OR IS NULL', $column];
        return $this;
    }

    public function orderByDesc(string $column)
    {
        $this->conditions[] = ['DESC', $column];
        return $this;
    }

    public function orWhereNotNull(string $column)
    {
        $this->conditions[] = ['OR IS NOT NULL', $column];
        return $this;
    }

    public function get(): array|string
    {
        try {
            $query = "SELECT * FROM {$this->table}";

            if (count($this->conditions) > 0) {
                $query .= " WHERE ";
                foreach ($this->conditions as $condition) {

                    switch ($condition) {
                        case $condition[0] === 'OR':
                            $query .= " OR {$condition[1]} {$condition[2]} '{$condition[3]}'";
                            break;

                        case $condition[0] === 'AND':
                            $query .= " AND {$condition[1]} {$condition[2]} '{$condition[3]}'";
                            break;

                        case $condition[0] === 'IS NULL':
                            $query .= " $condition[1] IS NULL";
                            break;

                        case $condition[0] === 'IS NOT NULL':
                            $query .= " $condition[1] IS NOT NULL";
                            break;

                        case $condition[0] === 'OR IS NULL':
                            $query .= " OR $condition[1] IS NULL";
                            break;

                        case $condition[0] === 'DESC':
                            $query .= " ORDER BY $condition[1] DESC";
                            break;

                        case $condition[0] === 'AND IS NULL':
                            $query .= " AND $condition[1] IS NULL";
                            break;

                        case $condition[0] === 'OR IS NOT NULL':
                            $query .= " OR $condition[1] IS NOT NULL";
                            break;

                        case $condition[0] === 'WHERE':
                            $query .= " {$condition[1]} {$condition[2]} '{$condition[3]}'";
                            break;
                    }
                }
            }

            // return $query;

            $this->statement = $this->connection->prepare($query);
            $this->statement->execute();
            return $this->statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Query failed: " . $e->getMessage());
            return [];
        }
    }

    public function query($query, $param = [])
    {
        try {
            $this->statement = $this->connection->prepare($query);
            $this->statement->execute($param);
            return $this->statement;
        } catch (PDOException $e) {
            error_log("Query failed: " . $e->getMessage());
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

    public function whereNotDeleted(string $table, $column = null, $direction = null)
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

    public function orderBy($column = null, $direction = null)
    {
        return ' ORDER BY ' . $column . ' ' . $direction;
    }

    // DB::whereNotDeleted('table_name', 'id', 'dir')->where('id', '=', 'something')


    public function whereDeleted(string $table)
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
     * @return array|null
     * @link https://github.com/williamug
     */
    public function find(string $table, string $column, string $operator, int|string $value)
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
            abort();
        }

        return $result;
    }


    public function insert(string $table, array $columns)
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

    public function count(): int
    {
        return count($this->statement->fetchAll());
    }

    public function sum(string $columnName): int
    {
        return array_sum(array_column($this->statement->fetchAll(PDO::FETCH_ASSOC), $columnName));
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
