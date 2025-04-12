<?php

namespace Pocketframe\PocketORM\Database;

use PDO;
use PDOException;

/**
 * Manages a singleton PDO connection instance.
 * Configure via .env or a config() helper.
 */
final class Connection
{
  private static ?PDO $instance = null;
  private static array $config = [];

  /**
   * Configure database parameters from a config array or .env.
   */
  public static function configure(): void
  {
    $config = config('database', []);
    $databaseConfig = $config['database'] ?? [];
    $driver = $databaseConfig['driver'] ?? 'mysql';

    // Driver-specific configuration
    $requirements = match ($driver) {
      'mysql' => [
        'required' => ['host', 'database', 'username'],
        'defaults' => [
          'port' => 3306,
          'charset' => 'utf8mb4',
          'collation' => 'utf8mb4_unicode_ci'
        ]
      ],
      'pgsql' => [
        'required' => ['host', 'database', 'username'],
        'defaults' => [
          'port' => 5432,
          'charset' => 'utf8',
        ]
      ],
      'sqlite' => [
        'required' => ['database'],
        'defaults' => []
      ],
      default => throw new PDOException("Unsupported driver: $driver")
    };

    // Validate required parameters
    foreach ($requirements['required'] as $key) {
      if (empty($databaseConfig[$key])) {
        throw new PDOException("Missing required database config: {$key}");
      }
    }

    // Merge with defaults
    self::$config = array_merge(
      [
        'driver' => $driver,
        'options' => [
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
          PDO::ATTR_EMULATE_PREPARES => false,
        ]
      ],
      $requirements['defaults'],
      $databaseConfig
    );

    // SQLite-specific setup
    if ($driver === 'sqlite') {
      self::$config['database'] = database_path(self::$config['database']);
      if (!file_exists(self::$config['database'])) {
        touch(self::$config['database']);
      }
    }
  }

  /**
   * Retrieve the singleton PDO instance.
   */
  public static function getInstance(): PDO
  {
    if (self::$instance === null) {
      try {
        $dsn = self::buildDsn();
        self::$instance = new PDO(
          $dsn,
          self::$config['username'] ?? null,
          self::$config['password'] ?? null,
          self::$config['options']
        );

        if (config('database.strict', false)) {
          self::$instance->exec("SET sql_mode='STRICT_ALL_TABLES'");
        }
      } catch (PDOException $e) {
        throw new PDOException("Connection failed: " . $e->getMessage());
      }
    }
    return self::$instance;
  }

  /**
   * Build the DSN string based on the configured driver.
   */
  private static function buildDsn(): string
  {
    return match (self::$config['driver']) {
      'sqlite' => self::buildSqliteDsn(),
      'mysql'  => sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        self::$config['host'],
        self::$config['port'],
        self::$config['database'],
        self::$config['charset']
      ),
      'pgsql'  => sprintf(
        'pgsql:host=%s;port=%d;dbname=%s',
        self::$config['host'],
        self::$config['port'],
        self::$config['database']
      ),
      default  => throw new PDOException("Unsupported driver: " . self::$config['driver'])
    };
  }

  private static function buildSqliteDsn(): string
  {
    $path = self::$config['database'];
    $dir = dirname($path);

    if (!is_dir($dir)) {
      mkdir($dir, 0755, true);
    }

    if (!file_exists($path)) {
      touch($path);
    }

    return 'sqlite:' . $path;
  }

  // Transaction helpers (optional convenience methods)
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
