<?php

namespace Pocketframe\PocketORM\Entity;

// same as model events
class HookDispatcher
{
  private static array $hooks = [];

  public static function listen(string $event, callable $callback): void
  {
    self::$hooks[$event][] = $callback;
  }

  public static function trigger(string $event, Entity $entity): void
  {
    foreach (self::$hooks[$event] ?? [] as $callback) {
      $callback($entity);
    }
  }
}
