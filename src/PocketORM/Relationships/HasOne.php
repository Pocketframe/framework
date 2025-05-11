<?php

namespace Pocketframe\PocketORM\Relationships;

use Pocketframe\PocketORM\QueryEngine\QueryEngine;
use Pocketframe\PocketORM\Entity\Entity;

/**
 * HasOne: one-to-one relationship where the parent "has one" child.
 */
class HasOne
{
  use RelationshipUtils;

  private Entity $parent;
  private string $related;
  private string $foreignKey;

  public function __construct(Entity $parent, string $related, string $foreignKey)
  {
    $this->parent     = $parent;
    $this->related    = $related;
    $this->foreignKey = $foreignKey;
  }

  public function deepFetch(array $parents, array $columns = ['*']): array
  {
    $parentIds = array_map(fn($p) => $p->id, $parents);
    $query = (new QueryEngine($this->related))->select($columns);
    $relatedRecords = $this->chunkedWhereIn($query, $this->foreignKey, $parentIds);
    // For HasOne, group by foreign key, but only keep the first found (if multiple)
    $grouped = [];
    foreach ($relatedRecords as $record) {
      $fk = $record->{$this->foreignKey} ?? null;
      if ($fk !== null && !isset($grouped[$fk])) {
        $grouped[$fk] = $record;
      }
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

  public function get(): ?object
  {
    return (new QueryEngine($this->related))
      ->where($this->foreignKey, '=', $this->parent->id)
      ->first();
  }
}
