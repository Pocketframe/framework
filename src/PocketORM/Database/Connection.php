<?php

namespace Pocketframe\PocketORM\Database;

use PDO;
use PDOException;
use Pocketframe\Exceptions\DatabaseException;

final class Connection
{
  private static ?PDO $instance = null;
  private static array $config = [];

  public static function configure(): void
  {
    $config = config('database', []);
    $databaseConfig = $config['database'] ?? [];

    // Validate required parameters
    $required = ['host', 'dbname', 'username'];
    foreach ($required as $key) {
      if (empty($databaseConfig[$key])) {
        throw new DatabaseException(
          "Missing required database config: {$key}",
          500
        );
      }
    }


    self::$config = [
      'driver' => $databaseConfig['driver'] ?? 'mysql',
      'host' => $databaseConfig['host'],
      'port' => $databaseConfig['port'] ?? 3306,
      'database' => $databaseConfig['dbname'],
      'username' => $databaseConfig['username'],
      'password' => $databaseConfig['password'] ?? '',
      'charset' => $databaseConfig['charset'] ?? 'utf8mb4',
      'collation' => $databaseConfig['collation'] ?? 'utf8mb4_unicode_ci',
      'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$databaseConfig['charset']} COLLATE {$databaseConfig['collation']}"
      ]
    ];
  }

  public static function getInstance(): PDO
  {
    if (self::$instance === null) {
      try {
        $dsn = self::buildDsn();

        self::$instance = new PDO(
          $dsn,
          self::$config['username'],
          self::$config['password'],
          self::$config['options']
        );

        if (config('database.strict', false)) {
          self::$instance->exec("SET sql_mode='STRICT_ALL_TABLES'");
        }
      } catch (\PDOException $e) {
        throw new DatabaseException("Connection failed: " . $e->getMessage());
      }
    }
    return self::$instance;
  }

  private static function buildDsn(): string
  {
    return match (self::$config['driver']) {
      'mysql' => sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        self::$config['host'],
        self::$config['port'],
        self::$config['database'],
        self::$config['charset']
      ),
      'pgsql' => sprintf(
        'pgsql:host=%s;port=%d;dbname=%s',
        self::$config['host'],
        self::$config['port'],
        self::$config['database']
      ),
      'sqlite' => 'sqlite:' . database_path(self::$config['database']),
      default => throw new DatabaseException("Unsupported driver: " . self::$config['driver'])
    };
  }

  public static function beginTransaction(): bool
  {
    return self::getInstance()->beginTransaction();
  }

  public static function commit(): bool
  {
    return self::getInstance()->commit();
  }

  public static function rollBack(): bool
  {
    return self::getInstance()->rollBack();
  }
}
