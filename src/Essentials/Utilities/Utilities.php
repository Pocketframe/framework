<?php

namespace Pocketframe\Essentials\Utilities;

class Utilities
{
  public static function classBasename(string|object $class): string
  {
    $className = is_object($class) ? get_class($class) : $class;
    return basename(str_replace('\\', '/', $className));
  }

  public static function now(): string
  {
    return date('Y-m-d H:i:s');
  }
}
