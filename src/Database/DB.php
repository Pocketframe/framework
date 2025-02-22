<?php

namespace Pocketframe\Database;

use Pocketframe\Container\App;
use Pocketframe\Container\Container;

class DB
{
  private static $instance = null;
  private static $container = null;

  /**
   * Set the container instance
   *
   * Sets the dependency injection container that will be used to resolve
   * the Database instance. This must be called before any database operations
   * are performed.
   *
   * @param Container $container The container instance to use
   * @return void
   */
  public static function setContainer(Container $container)
  {
    self::$container = $container;
  }

  /**
   * Get the Database instance
   *
   * Returns the Database instance that is currently being used. If no instance
   * has been created yet, it will be created using the container.
   *
   * @return Database
   */
  public static function getInstance()
  {
    if (!self::$instance) {
      if (!self::$container) {
        throw new \Exception("Container not set");
      }
      self::$instance = self::$container->get(Database::class);
    }
    return self::$instance;
  }

  /**
   * Dynamically call methods on the Database instance
   *
   * This method allows calling any method on the Database instance
   * using the DB class. It retrieves the instance and then calls
   * the specified method with the provided arguments.
   *
   * @param string $method The method to call on the Database instance
   * @param array $args The arguments to pass to the method
   * @return mixed The result of the method call
   */
  public static function __callStatic($method, $args)
  {
    $instance = self::getInstance();
    return call_user_func_array([$instance, $method], $args);
  }
}
