<?php

namespace Pocketframe\PocketORM\Data;

class PlanterRegistry
{
  private static array $planters = [];

  public static function register(string $planterClass): void
  {
    self::$planters[] = $planterClass;
  }

  public static function plantAll(): void
  {
    foreach (self::$planters as $planter) {
      $planter::run();
    }
  }
}
