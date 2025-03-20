<?php

declare(strict_types=1);

namespace Pocketframe\Masks;

use Pocketframe\Validation\Validator as ValidationValidator;

class Validator
{
  private static $instance;


  /**
   * Static wrapper for the `Validator` class.
   *
   * @param string $method
   * @param array $arguments
   * @return mixed
   */
  public static function __callStatic(string $method, array $arguments)
  {
    if (!self::$instance) {
      self::$instance = new ValidationValidator();
    }

    $result = self::$instance->$method(...$arguments);
    return $result instanceof ValidationValidator ? self::$instance : $result;
  }
}
