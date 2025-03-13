<?php

declare(strict_types=1);

namespace Pocketframe\Storage\Facade;

class Storage
{
  protected static ?Storage $instance = null;

  /**
   * Get the underlying Storage instance.
   *
   * @param string|null $disk The disk name (defaults to the configured default disk)
   * @return Storage
   */
  public static function getInstance(?string $disk = null): Storage
  {
    if (!self::$instance) {
      self::$instance = new Storage($disk);
    }
    return self::$instance;
  }

  /**
   * Handle dynamic, static calls to the object.
   *
   * @param string $method
   * @param array $args
   * @return mixed
   */
  public static function __callStatic(string $method, array $args)
  {
    return call_user_func_array([self::getInstance(), $method], $args);
  }
}
