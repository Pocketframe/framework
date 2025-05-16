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
    $this->parent      = $parent;
    $this->related     = $related;
    $this->bridgeTable = $bridgeTable;
    $this->parentKey   = $parentKey;
    $this->relatedKey  = $relatedKey;
  }


  public function deepFetch(array $parents, array $columns = ['*']): array
  {
    // fallback (unfiltered) logic stays the same
    $engine = new QueryEngine($this->related);
    return $this->deepFetchUsingEngine($parents, $engine->select($columns));
  }

  public function deepFetchUsingEngine(array $parents, QueryEngine $engine): array
  {
    $parentIds  = array_map(fn(Entity $p) => $p->id, $parents);
    $bridgeRows = (new QueryEngine($this->bridgeTable))
      ->whereIn($this->parentKey, $parentIds)
      ->get()
      ->all();

    $relatedIds = array_map(
      fn($r) =>
      is_array($r) ? $r[$this->relatedKey] : $r->{$this->relatedKey},
      $bridgeRows
    );

    $relatedRecs = $this->chunkedWhereIn($engine, 'id', $relatedIds);

    $indexed = [];
    foreach ($relatedRecs as $rec) {
      $indexed[$rec->id] = $rec;
    }

    $mapped = [];
    foreach ($bridgeRows as $row) {
      $pid = is_array($row) ? $row[$this->parentKey] : $row->{$this->parentKey};
      $rid = is_array($row) ? $row[$this->relatedKey] : $row->{$this->relatedKey};
      if (isset($indexed[$rid])) {
        $mapped[$pid][] = $indexed[$rid];
      }
    }

    return $mapped;
  }


  public function get(): DataSet
  {
    // unchanged
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

    (new QueryEngine($this->bridgeTable))
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
