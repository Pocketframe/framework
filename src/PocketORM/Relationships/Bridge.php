<?php

namespace Pocketframe\PocketORM\Relationships;

use Pocketframe\PocketORM\Entity\Entity;
use Pocketframe\PocketORM\Essentials\DataSet;
use Pocketframe\PocketORM\Exceptions\RelationshipResolutionError;
use Pocketframe\PocketORM\QueryEngine\QueryEngine;

/**
 * Bridge: represents a many-to-many relationship via a pivot table.
 */
class Bridge
{
  use RelationshipUtils;

  private Entity $parent;
  private string $related;
  private string $bridgeTable;
  private string $parentKey;
  private string $relatedKey;

  public function __construct(Entity $parent, string $related, string $bridgeTable, string $parentKey, string $relatedKey)
  {
    $this->parent     = $parent;
    $this->related    = $related;
    $this->bridgeTable = $bridgeTable;
    $this->parentKey  = $parentKey;
    $this->relatedKey = $relatedKey;
  }

  public function deepFetch(array $parents, array $columns = ['*']): array
  {
    $parentIds = array_map(fn($p) => $p->id, $parents);
    $bridgeData = (new QueryEngine($this->bridgeTable))
      ->whereIn($this->parentKey, $parentIds)
      ->get()
      ->all();

    $relatedIds = array_column($bridgeData, $this->relatedKey);
    $relatedQuery = (new QueryEngine($this->related))->select($columns);
    $relatedRecords = $this->chunkedWhereIn($relatedQuery, 'id', $relatedIds);

    // Index by ID for fast lookup
    $indexed = [];
    foreach ($relatedRecords as $record) {
      $indexed[$record->id] = $record;
    }

    $mapped = [];
    foreach ($bridgeData as $bridge) {
      $parentId = $bridge[$this->parentKey];
      $relatedId = $bridge[$this->relatedKey];
      if (isset($indexed[$relatedId])) {
        $mapped[$parentId][] = $indexed[$relatedId];
      }
    }

    return $mapped;
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

    return (new QueryEngine($this->bridgeTable))
      ->withTrashed()
      ->select([$this->related::getTable() . '.*'])
      ->join(
        $this->related::getTable(),
        "{$this->bridgeTable}.{$this->relatedKey}",
        '=',
        "{$this->related::getTable()}.id"
      )
      ->where("{$this->bridgeTable}.{$this->parentKey}", '=', $this->parent->id)
      ->get();
  }

  public function getForeignKey(): string
  {
    return $this->relatedKey;
  }

  public function getParentKey(): string
  {
    return $this->parentKey;
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

    (new QueryEngine($this->bridgeTable))
      ->insertBatch($insertData);
  }

  public function detach($relatedId): void
  {
    if (!isset($this->parent->id)) {
      throw new \RuntimeException("Cannot detach - parent entity lacks an ID");
    }

    (new QueryEngine($this->bridgeTable, $this->related))
      ->where($this->parentKey, '=', $this->parent->id)
      ->where($this->relatedKey, '=', $relatedId)
      ->delete();
  }

  public function sync(array $relatedIds): void
  {
    if (!isset($this->parent->id)) {
      throw new \RuntimeException("Cannot sync relationships - parent entity lacks an ID");
    }

    // Delete all existing bridge entries for this parent
    (new QueryEngine($this->bridgeTable))
      ->where($this->parentKey, '=', $this->parent->id)
      ->delete();

    // Attach the new IDs
    $this->attach($relatedIds);
  }
}
