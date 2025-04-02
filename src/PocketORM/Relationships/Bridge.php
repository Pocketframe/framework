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

  public function __construct(
    Entity $parent,
    string $related,
    string $pivotTable,
    string $parentKey,
    string $relatedKey
  ) {
    $this->parent     = $parent;
    $this->related    = $related;
    $this->pivotTable = $pivotTable;
    $this->parentKey  = $parentKey;
    $this->relatedKey = $relatedKey;
  }

  public function eagerLoad(array $parents): array
  {
    $parentIds = array_column(array_map(fn($p) => (array)$p, $parents), 'id');

    $pivotData = (new QueryEngine($this->pivotTable, $this->related))
      ->whereIn($this->parentKey, $parentIds)
      ->get()
      ->all();

    $relatedIds = array_unique(
      array_column(array_map(fn($p) => (array)$p, $pivotData), $this->relatedKey)
    );

    $relatedRecords = (new QueryEngine($this->related))
      ->whereIn('id', $relatedIds)
      ->keyBy('id')
      ->get();

    $mapped = [];
    foreach ($pivotData as $pivot) {
      $pivotArray = (array)$pivot;
      $mapped[$pivotArray[$this->parentKey]][] =
        $relatedRecords[$pivotArray[$this->relatedKey]] ?? null;
    }

    return $mapped;
  }

  public function getParentKey(): string
  {
    return $this->parentKey ?? 'id';
  }

  public function get(): DataSet
  {
    if (!class_exists($this->related)) {
      throw new RelationshipResolutionError(
        "Related class {$this->related} does not exist",
        $this->related
      );
    }

    return (new QueryEngine($this->pivotTable, $this->related))
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

  public function attach($relatedId): void
  {
    (new QueryEngine($this->pivotTable, $this->related::class))
      ->insert([
        $this->parentKey => $this->parent->id,
        $this->relatedKey => $relatedId
      ]);
  }

  public function detach($relatedId): void
  {
    (new QueryEngine($this->pivotTable, $this->related::class))
      ->where($this->parentKey, '=', $this->parent->id)
      ->where($this->relatedKey, '=', $relatedId)
      ->delete();
  }
}
