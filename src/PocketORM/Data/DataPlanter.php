<?php

namespace Pocketframe\PocketORM\Data;

use Pocketframe\PocketORM\Database\Connection;

// same as seeder
abstract class DataPlanter
{
  use UsesBlueprints;
  /**
   * Plant data
   *
   * @throws \Exception
   */
  abstract public function plant(): void;

  /**
   * Run a planter
   *
   * @throws \InvalidArgumentException
   */
  public static function run(): void
  {
    (new static())->plant();
  }


  /**
   * Run multiple planters in sequence
   *
   * @param array $planters Array of planter class names or ['class' => count] pairs
   * @param bool $quiet Suppress output if true
   * @throws \InvalidArgumentException
   */
  protected function runPlanters(array $planters, bool $quiet = false): void
  {
    foreach ($planters as $key => $value) {
      if (is_int($key)) {
        $this->runSinglePlanter($value, 1, $quiet);
      } else {
        $this->runSinglePlanter($key, $value, $quiet);
      }
    }
  }


  /**
   * Run a single planter multiple times
   *
   * @param string $planterClass Fully qualified class name
   * @param int $times Number of times to run
   * @param bool $quiet Suppress output if true
   * @throws \InvalidArgumentException
   */
  protected function runSinglePlanter(string $planterClass, int $times = 1, bool $quiet = false): void
  {
    $this->validatePlanterClass($planterClass);

    if (!$quiet) {
      echo "🌱 Planting Data: {$planterClass}" . ($times > 1 ? " x{$times}" : '') . "\n";
    }

    for ($i = 0; $i < $times; $i++) {
      $planterClass::run();
    }
  }

  /**
   * Validate a planter class
   *
   * @param string $planterClass
   * @throws \InvalidArgumentException
   */
  protected function validatePlanterClass(string $planterClass): void
  {
    if (!class_exists($planterClass)) {
      throw new \InvalidArgumentException("Planter class not found: {$planterClass}");
    }

    if (!is_subclass_of($planterClass, self::class)) {
      throw new \InvalidArgumentException(
        "Invalid planter class: {$planterClass} must extend " . self::class
      );
    }
  }

  /**
   * Insert batch of rows into a table
   *
   * @param string $table Table name
   * @param array $data Array of rows to insert
   */
  protected function insertBatch(string $table, array $data): void
  {
    $columns = implode(', ', array_keys($data[0]));
    $placeholders = implode(', ', array_fill(0, count($data[0]), '?'));

    $stmt = Connection::getInstance()->prepare(
      "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})"
    );

    foreach ($data as $row) {
      $stmt->execute(array_values($row));
    }
  }
}
