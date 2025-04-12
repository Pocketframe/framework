<?php

namespace Pocketframe\PocketORM\Schema;

use PDO;
use Pocketframe\PocketORM\Database\Connection;

class Schema
{
  private static $columnCache = [];

  public static function tableHasColumn(string $table, string $column): bool
  {
    if (!isset(self::$columnCache[$table])) {
      self::$columnCache[$table] = self::getTableColumns($table);
    }

    return in_array($column, self::$columnCache[$table], true);
  }

  private static function getTableColumns(string $table): array
  {
    $db = Connection::getInstance();
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

    switch ($driver) {
      case 'mysql':
        $stmt = $db->query("DESCRIBE `{$table}`");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        break;
      case 'sqlite':
        $stmt = $db->query("PRAGMA table_info(`{$table}`)");
        $columns = array_column($stmt->fetchAll(), 'name');
        break;
      case 'pgsql':
        $stmt = $db->query(
          "SELECT column_name FROM information_schema.columns
                     WHERE table_name = '{$table}'"
        );
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        break;
      default:
        throw new \RuntimeException("Unsupported database driver: {$driver}");
    }

    return $columns;
  }
}
