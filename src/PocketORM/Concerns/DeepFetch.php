<?php

namespace Pocketframe\PocketORM\Concerns;

use Pocketframe\PocketORM\Entity\Entity;
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
   * Batch deep fetch relationships for multiple records at once.
   */
  private function batchLoad(DataSet $records, array $relations): void
  {
    $allRecords = $records->all();
    if (empty($allRecords)) return;

    $relation = array_shift($relations);
    $first = reset($allRecords);

    $config = $first->getRelationshipConfig($relation);
    $relationshipClass = $config[0];
    $args = $this->prepareRelationshipArgs($first, $config);
    $relationship = new $relationshipClass(...$args);

    $relatedMap = $relationship->deepFetch($allRecords);

    // Determine lookup key based on relationship type
    if ($relationship instanceof Bridge || $relationship instanceof HasMultiple) {
      // For Bridge and HasMultiple, use parent's ID
      $lookupKey = 'id';
    } else {
      // For OwnedBy/HasOne, use foreign key from parent
      $lookupKey = $relationship->getForeignKey();
    }

    foreach ($allRecords as $parent) {
      $keyValue = $parent->{$lookupKey};
      $parent->setDeepFetch(
        $relation,
        $this->formatLoadedData($relationship, $relatedMap[$keyValue] ?? [])
      );
    }

    if (!empty($relations)) {
      $relatedRecords = new DataSet(array_merge(...array_values($relatedMap)));
      $this->batchLoad($relatedRecords, $relations);
    }
  }


  private function prepareRelationshipArgs(Entity $parent, array $config): array
  {
    $relationshipClass = $config[0];

    // Handle Bridge relationships specially
    if ($relationshipClass === Bridge::class) {
      return [
        $parent,        // Parent entity
        $config[1],     // Related class
        $config[2],     // Pivot table
        $config[3],     // Parent key
        $config[4]      // Related key
      ];
    }

    // Default handling for other relationships
    return [
      $parent,        // Parent entity
      $config[1],     // Related class
      $config[2] ?? null // Foreign key
    ];
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
