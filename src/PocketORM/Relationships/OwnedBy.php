<?php

namespace Pocketframe\PocketORM\Relationships;

use Pocketframe\PocketORM\QueryEngine\QueryEngine;
use Pocketframe\PocketORM\Entity\Entity;

/**
 * OwnedBy: belongs-to relationship (the parent "owns" a foreign key referencing another entity's primary key).
 */
class OwnedBy
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
    // Extract foreign keys directly from each parent entity
    $foreignKeys = array_map(fn($p) => $p->{$this->foreignKey}, $parents);
    $query = (new QueryEngine($this->related))->select($columns);
    $relatedRecords = $this->chunkedWhereIn($query, 'id', $foreignKeys);

    // Group related records by their ID for easy lookup
    $grouped = [];
    foreach ($relatedRecords as $record) {
      $id = $record->id ?? null;
      if ($id !== null) {
        $grouped[$id] = $record;
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

  public function resolve(): ?object
  {
    $fkValue = $this->parent->{$this->foreignKey};
    if ($fkValue === null) {
      return null;
    }

    return (new QueryEngine($this->related))
      ->where('id', '=', $fkValue)
      ->first();
  }
}
