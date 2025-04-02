<?php

namespace Pocketframe\PocketORM\Relationships;

use Pocketframe\PocketORM\Database\QueryEngine;
use Pocketframe\PocketORM\Essentials\DataSet;
use Pocketframe\PocketORM\Entity\Entity;

/**
 * HasMultiple: one-to-many relationship (a parent has multiple children).
 */
class HasMultiple
{
  private Entity $parent;
  private string $related;
  private string $foreignKey;

  public function __construct(Entity $parent, string $related, string $foreignKey)
  {
    $this->parent     = $parent;
    $this->related    = $related;
    $this->foreignKey = $foreignKey;
  }

  public function eagerLoad(array $parents): array
  {
    $parentIds = array_column($parents, 'id');


    // Get all related records as proper entities
    $relatedRecords = (new QueryEngine($this->related))
      ->whereIn($this->foreignKey, $parentIds)
      ->get()
      ->all();

    $grouped = [];
    foreach ($relatedRecords as $record) {
      $foreignKeyValue = $record->{$this->foreignKey};
      $grouped[$foreignKeyValue][] = $record;
    }

    return $grouped;
  }

  public function getForeignKey(): string
  {
    return $this->foreignKey;
  }

  public function getParentKey(): string
  {
    return 'id';
  }

  public function get(): DataSet
  {
    return (new QueryEngine($this->related))
      ->where($this->foreignKey, '=', $this->parent->id)
      ->get();
  }
}
