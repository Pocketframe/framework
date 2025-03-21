<?php

namespace Pocketframe\PocketORM\Relationships;

use Pocketframe\PocketORM\Database\QueryEngine;
use Pocketframe\PocketORM\Entity\Entity;
use Pocketframe\PocketORM\Essentials\RecordSet;
use Pocketframe\PocketORM\Exceptions\RelationshipResolutionError;

// same as many to many
class Bridge
{
  private $parent;
  private $related;
  private $pivotTable;
  private $parentKey;
  private $relatedKey;

  public function __construct(
    Entity $parent,
    string $related,
    string $pivotTable,
    string $parentKey,
    string $relatedKey
  ) {
    $this->parent = $parent;
    $this->related = $related;
    $this->pivotTable = $pivotTable;
    $this->parentKey = $parentKey;
    $this->relatedKey = $relatedKey;
  }

  public function get(): RecordSet
  {
    if (!class_exists($this->related)) {
      throw new RelationshipResolutionError(
        "Related class {$this->related} does not exist",
        $this->related
      );
    }

    return (new QueryEngine($this->pivotTable))
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
    (new QueryEngine($this->pivotTable))
      ->insert([
        $this->parentKey => $this->parent->id,
        $this->relatedKey => $relatedId
      ]);
  }

  public function detach($relatedId): void
  {
    (new QueryEngine($this->pivotTable))
      ->where($this->parentKey, '=', $this->parent->id)
      ->where($this->relatedKey, '=', $relatedId)
      ->delete();
  }
}
