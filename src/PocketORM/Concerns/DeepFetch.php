<?php

namespace Pocketframe\PocketORM\Concerns;

use Pocketframe\PocketORM\Essentials\RecordSet;

// same as eager loading
trait DeepFetch
{
  private array $includes = [];

  public function include(string $relation): self
  {
    $this->includes[] = $relation;
    return $this;
  }

  public function get(): RecordSet
  {
    $records = parent::get();

    foreach ($this->includes as $relation) {
      $this->loadRelation($records, $relation);
    }

    return $records;
  }

  private function loadRelation(RecordSet $records, string $relation): void
  {
    $relations = explode('.', $relation);
    $this->nestedLoad($records, $relations);
  }

  private function nestedLoad(RecordSet $records, array $relations): void
  {
    // Implementation for nested eager loading
  }
}
