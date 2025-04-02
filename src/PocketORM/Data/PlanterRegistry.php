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

  public static function discoverAndRegister(): void
  {
    $planters = glob(database_path('planters/*.php'));

    foreach ($planters as $planter) {
      require_once $planter;

      $className = 'Database\\Planters\\' . basename($planter, '.php');
      if (class_exists($className)) {
        self::register($className);
      }
    }
  }
}
