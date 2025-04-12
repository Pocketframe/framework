<?php

namespace Pocketframe\PocketORM\Relationships;

use Pocketframe\PocketORM\Database\QueryEngine;
use Pocketframe\PocketORM\Entity\Entity;
use Pocketframe\PocketORM\Essentials\DataSet;
use Pocketframe\PocketORM\Exceptions\RelationshipResolutionError;

/**
 * Bridge: represents a many-to-many relationship via a pivot table.
 */
class Bridge
{
  private Entity $parent;
  private string $related;
  private string $pivotTable;
  private string $parentKey;
  private string $relatedKey;

  public function __construct(Entity $parent, string $related, string $pivotTable, string $parentKey, string $relatedKey)
  {
    $this->parent     = $parent;
    $this->related    = $related;
    $this->pivotTable = $pivotTable;
    $this->parentKey  = $parentKey;
    $this->relatedKey = $relatedKey;
  }

  public function eagerLoad(array $parents): array
  {
    $parentIds = array_map(fn($parent) => (int)$parent->id, $parents);

    // Disable soft delete filtering on the pivot table
    $pivotData = (new QueryEngine($this->pivotTable))
      ->withTrashed()
      ->whereIn($this->parentKey, $parentIds)
      ->get()
      ->toArray();

    // Extract unique related IDs from pivot data
    $relatedIds = array_map(
      'intval',
      array_unique(array_column($pivotData, $this->relatedKey))
    );

    // Fetch related IDs
    $relatedRecordsRaw = (new QueryEngine($this->related))
      ->whereIn('id', $relatedIds)
      ->withTrashed()
      ->get()
      ->all();

    $relatedRecords = [];
    foreach ($relatedRecordsRaw as $record) {
      $relatedRecords[$record->id] = $record;
    }


    // Map parent IDs to their tags
    $mapped = [];
    foreach ($pivotData as $pivot) {
      $parentId = (int)$pivot[$this->parentKey];
      $relatedId = (int)$pivot[$this->relatedKey];

      if (isset($relatedRecords[$relatedId])) {
        $mapped[$parentId][] = $relatedRecords[$relatedId];
      }
    }

    return $mapped;
  }

  public function getParentKey(): string
  {
    return $this->parentKey ?? 'id';
  }

  public function get(): DataSet
  {
    if (!isset($this->parent->id)) {
      throw new \RuntimeException("Cannot fetch relationship - parent entity lacks an ID");
    }

    if (!class_exists($this->related)) {
      throw new RelationshipResolutionError(
        "Related class {$this->related} does not exist",
        $this->related
      );
    }

    return (new QueryEngine($this->pivotTable))
      ->withTrashed()
      ->select([$this->related::getTable() . '.*'])
      ->join(
        $this->related::getTable(),
        "{$this->pivotTable}.{$this->relatedKey}",
        '=',
        "{$this->related::getTable()}.id"
      )
      ->where("{$this->pivotTable}.{$this->parentKey}", '=', $this->parent->id)
      ->get();
  }

  public function attach($relatedIds): void
  {
    if (!is_array($relatedIds)) {
      $relatedIds = [$relatedIds];
    }

    // Validate IDs are integers
    foreach ($relatedIds as $id) {
      if (!is_numeric($id)) {
        throw new \InvalidArgumentException(
          "Invalid related ID: " . print_r($id, true)
        );
      }
    }

    $insertData = array_map(fn($id) => [
      $this->parentKey => $this->parent->id,
      $this->relatedKey => (int) $id
    ], $relatedIds);

    (new QueryEngine($this->pivotTable))
      ->insertBatch($insertData);
  }

  public function detach($relatedId): void
  {
    if (!isset($this->parent->id)) {
      throw new \RuntimeException("Cannot detach - parent entity lacks an ID");
    }

    (new QueryEngine($this->pivotTable, $this->related))
      ->where($this->parentKey, '=', $this->parent->id)
      ->where($this->relatedKey, '=', $relatedId)
      ->delete();
  }

  public function sync(array $relatedIds): void
  {
    if (!isset($this->parent->id)) {
      throw new \RuntimeException("Cannot sync relationships - parent entity lacks an ID");
    }

    // Delete all existing pivot entries for this parent
    (new QueryEngine($this->pivotTable))
      ->where($this->parentKey, '=', $this->parent->id)
      ->delete();

    // Attach the new IDs
    $this->attach($relatedIds);
  }
}
