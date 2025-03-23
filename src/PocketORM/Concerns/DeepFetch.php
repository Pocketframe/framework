<?php

namespace Pocketframe\PocketORM\Concerns;

use Pocketframe\PocketORM\Essentials\RecordSet;
use Pocketframe\PocketORM\Essentials\DataSet;
use Pocketframe\PocketORM\Relationships\Bridge;
use Pocketframe\PocketORM\Relationships\HasMultiple;
use Pocketframe\PocketORM\Relationships\HasOne;
use Pocketframe\PocketORM\Relationships\OwnedBy;

trait DeepFetch
{
  private array $includes = [];

  /**
   * Specify relationships to include.
   */
  public function include(string|array $relation): self
  {
    $this->includes = array_merge($this->includes, (array) $relation);
    return $this;
  }

  /**
   * Get the records along with the requested relationships.
   */
  public function get(): DataSet
  {
    $records = parent::get(); // Call parent get() to fetch base records

    foreach ($this->includes as $relation) {
      $this->loadRelation($records, $relation);
    }

    return $records;
  }

  /**
   * Load a specific relation for all records.
   */
  private function loadRelation(DataSet $records, string $relation): void
  {
    $relations = explode('.', $relation);
    $this->batchLoad($records, $relations);
  }

  /**
   * Batch eager load relationships for multiple records at once.
   */
  private function batchLoad(DataSet $records, array $relations): void
  {
    if (empty($records->all())) return;

    $relation = array_shift($relations);
    $first = reset($records->all());

    if (!isset($first->relationship[$relation])) {
      throw new \Exception("Relationship '{$relation}' is not defined in " . get_class($first));
    }

    $config = $first->relationship[$relation];
    [$relationshipClass, $relatedEntity, $foreignKey] = $config;

    $relationship = new $relationshipClass($first, $relatedEntity, $foreignKey ?? null);
    $relatedMap = $relationship->eagerLoad($records->all());

    foreach ($records->all() as $parent) {
      $key = $parent->{$relationship->foreignKey} ?? $parent->id;

      if ($key !== null) {
        $parent->eagerLoaded[$relation] = $this->formatLoadedData($relationship, $relatedMap[$key] ?? []);
      }
    }

    // If there are more nested relations, recursively load them
    if (!empty($relations)) {
      $relatedRecords = new DataSet(array_merge(...array_values($relatedMap)));
      $this->batchLoad($relatedRecords, $relations);
    }
  }

  /**
   * Format the loaded relationship data based on its type.
   */
  private function formatLoadedData($relationship, $data)
  {
    return match (true) {
      $relationship instanceof HasOne,
      $relationship instanceof OwnedBy => $data ?? null,

      $relationship instanceof HasMultiple,
      $relationship instanceof Bridge => new DataSet($data ?? []),

      default => null,
    };
  }
}
