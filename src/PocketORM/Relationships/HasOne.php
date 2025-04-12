<?php

namespace Pocketframe\PocketORM\Relationships;

use Pocketframe\PocketORM\Database\QueryEngine;
use Pocketframe\PocketORM\Entity\Entity;
use Pocketframe\PocketORM\Essentials\DataSet;

/**
 * HasOne: one-to-one relationship where the parent "has one" child.
 */
class HasOne
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
    $parentIds = [];
    foreach ($parents as $parent) {
      $parentIds[] = $parent->id;
    }

    // Fetch related records where the child's foreign key is in the parent's ids
    return (new QueryEngine($this->related))
      ->whereIn($this->foreignKey, array_unique($parentIds))
      ->keyBy('id')
      ->get()
      ->all();
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
