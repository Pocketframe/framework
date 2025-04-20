<?php

namespace Pocketframe\PocketORM\Database;

use PDO;
use PDOException;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Connection as DbalConnection;

/**
 * Manages a singleton PDO and DBAL connection instance.
 * Reads configuration from Laravel-style config/database.php.
 */
final class Connection
{
  private static ?PDO $instance = null;
  private static ?DbalConnection $dbalInstance = null;
  private static array $config = [];

  /**
   * Configure database parameters using config/database.php
   */
  public static function configure(): void
  {
    $databaseConfig = config('database', []);
    $default = $databaseConfig['default'] ?? 'mysql';
    $connections = $databaseConfig['connections'] ?? [];
    $selected = $connections[$default] ?? null;

    if (!$selected || !isset($selected['driver'])) {
      throw new PDOException("Invalid or missing database configuration for connection: {$default}");
    }

    $driver = $selected['driver'];

    // Basic defaults
    $defaults = [
      'host' => '127.0.0.1',
      'port' => match ($driver) {
        'mysql' => 3306,
        'pgsql' => 5432,
        'sqlsrv' => 1433,
        default => null,
      },
      'charset' => 'utf8mb4',
      'collation' => 'utf8mb4_unicode_ci',
      'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
        PDO::ATTR_EMULATE_PREPARES => false,
      ],
    ];

    self::$config = array_merge($defaults, $selected);

    // Normalize SQLite path
    if ($driver === 'sqlite') {
      $db = self::$config['database'] ?? null;
      if ($db !== ':memory:') {
        self::$config['database'] = database_path($db);
        if (!file_exists(self::$config['database'])) {
          touch(self::$config['database']);
        }
      }
    }
  }

  /**
   * Get the singleton PDO instance
   */
  public static function getInstance(): PDO
  {
    if (self::$instance === null) {
      self::configure();

      try {
        $dsn = self::buildDsn();
        self::$instance = new PDO(
          $dsn,
          self::$config['username'] ?? null,
          self::$config['password'] ?? null,
          self::$config['options'] ?? []
        );

        if (self::$config['strict'] ?? false) {
          self::$instance->exec("SET sql_mode='STRICT_ALL_TABLES'");
        }
      } catch (PDOException $e) {
        throw new PDOException("Connection failed: " . $e->getMessage());
      }
    }

    return self::$instance;
  }

  /**
   * Build the DSN string for the connection
   */
  private static function buildDsn(): string
  {
    return match (self::$config['driver']) {
      'sqlite' => self::buildSqliteDsn(),
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
      'sqlsrv' => sprintf(
        'sqlsrv:Server=%s,%d;Database=%s',
        self::$config['host'],
        self::$config['port'],
        self::$config['database']
      ),
      default => throw new PDOException("Unsupported driver: " . self::$config['driver'])
    };
  }

  private static function buildSqliteDsn(): string
  {
    $path = self::$config['database'];

    if ($path !== ':memory:') {
      $dir = dirname($path);
      if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
      }

      if (!file_exists($path)) {
        touch($path);
      }
    }

    return 'sqlite:' . $path;
  }

  /**
   * Get the singleton Doctrine DBAL connection
   */
  public static function getDoctrineConnection(): DbalConnection
  {
    if (self::$dbalInstance === null) {
      self::configure();

      $params = [
        'driver' => match (self::$config['driver']) {
          'mysql' => 'pdo_mysql',
          'pgsql' => 'pdo_pgsql',
          'sqlite' => 'pdo_sqlite',
          'sqlsrv' => 'pdo_sqlsrv',
          default => throw new PDOException("Unsupported driver for DBAL: " . self::$config['driver'])
        },
        'user' => self::$config['username'] ?? '',
        'password' => self::$config['password'] ?? '',
        'host' => self::$config['host'] ?? null,
        'port' => self::$config['port'] ?? null,
        'dbname' => self::$config['database'] ?? null,
      ];

      if (self::$config['driver'] === 'sqlite') {
        $params['path'] = self::$config['database'];
        unset($params['dbname']);
      }

      self::$dbalInstance = DriverManager::getConnection($params);
    }

    return self::$dbalInstance;
  }

  /** Transaction helpers */
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

  /** Force reset connection (used for testing or reconfiguration) */
  public static function reset(): void
  {
    self::$instance = null;
    self::$dbalInstance = null;
    self::$config = [];
  }
}
