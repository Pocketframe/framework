<?php

namespace Pocketframe\Console\Commands;

use Database\Planters\DatabasePlanter;
use Pocketframe\Contracts\CommandInterface;
use Pocketframe\PocketORM\Data\PlanterRegistry;

class PlantCommand implements CommandInterface
{
  protected array $args;

  public function __construct(array $args)
  {
    $this->args = $args;
  }

  public function handle(): void
  {
    $specificPlanter = $this->args[0] ?? null;

    if ($specificPlanter) {
      $this->runSpecificPlanter($specificPlanter);
    } else {
      $this->runAllPlanters();
    }
  }

  protected function runAllPlanters(): void
  {
    echo "🌱 Planting data...\n";

    // Option 1: Run through the DatabasePlanter
    DatabasePlanter::run();

    // Option 2: Run all registered planters
    // PlanterRegistry::plantAll();

    echo "✅ Data planted successfully!\n";
  }

  protected function runSpecificPlanter(string $planterClass): void
  {
    $planterClass = "Database\\Planters\\{$planterClass}";
    if (!class_exists($planterClass)) {
      echo "❌ Planter class not found: {$planterClass}\n";
      return;
    }

    if (!is_subclass_of($planterClass, \Pocketframe\PocketORM\Data\DataPlanter::class)) {
      echo "❌ Invalid planter class: must extend DataPlanter\n";
      return;
    }

    echo "🌱 Planting data with {$planterClass}...\n";
    $planterClass::run();
    echo "✅ Planter completed successfully!\n";
  }
}
