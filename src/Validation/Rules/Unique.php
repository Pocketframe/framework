<?php

declare(strict_types=1);

namespace Pocketframe\Validation\Rules;

use Pocketframe\Contracts\Rule;
use Pocketframe\Database\DB;
use PDO;

class Unique implements Rule
{
  protected string $table;
  protected string $column;
  protected ?int $ignoreId;

  /**
   * @param string $table The database table name.
   * @param string $column The column to check for uniqueness.
   * @param int|null $ignoreId Optional ID to ignore (useful during updates).
   */
  public function __construct(string $table, string $column, ?int $ignoreId = null)
  {
    $this->table = $table;
    $this->column = $column;
    $this->ignoreId = $ignoreId;
  }

  public function isValid(mixed $value): bool
  {
    // Build the base SQL query.
    $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE {$this->column} = ?";
    $params = [$value];

    // If an ID should be ignored, add it to the query.
    if ($this->ignoreId !== null) {
      $sql .= " AND id != ?";
      $params[] = $this->ignoreId;
    }

    // Use the DB class to run the query.
    $statement = DB::getInstance()->query($sql, $params);

    // Make sure we have a PDOStatement and fetch the count.
    if (is_object($statement)) {
      $result = $statement->fetch(PDO::FETCH_ASSOC);
      return $result['total'] == 0;
    }

    return false;
  }

  public function message(string $field): string
  {
    return "The :attribute already exists.";
  }
}
