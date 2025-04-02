<?php

namespace Pocketframe\PocketORM\Data;

trait UsesBlueprints
{
  /**
   * Get a blueprint instance
   *
   * @param string $blueprintClass Blueprint class
   * @return \Pocketframe\PocketORM\Data\Blueprint Blueprint instance
   */
  protected function blueprint(string $blueprintClass): Blueprint
  {
    return new $blueprintClass();
  }

  /**
   * Create records using a blueprint
   *
   * @param string $blueprintClass Blueprint class
   * @param int $count Number of records to create
   * @param array $overrides Optional overrides for the records
   * @return array Array of created records
   */
  protected function createUsing(string $blueprintClass, int $count = 1, array $overrides = []): array
  {
    return $this->blueprint($blueprintClass)->createMany($count, $overrides);
  }

  /**
   * Make (but don't save) records using a blueprint
   *
   * @param string $blueprintClass Blueprint class
   * @param int $count Number of records to make
   * @param array $overrides Optional overrides for the records
   * @return array Array of records
   */
  protected function makeUsing(string $blueprintClass, int $count = 1, array $overrides = []): array
  {
    return $this->blueprint($blueprintClass)->makeMany($count, $overrides);
  }
}
