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
    $parentIds = array_column(array_map(fn($p) => (array)$p, $parents), 'id');

    $relatedRecords = (new QueryEngine($this->related::getTable(), $this->related::class))
      ->whereIn($this->foreignKey, $parentIds)
      ->groupBy($this->foreignKey)
      ->get();

    return $relatedRecords->all();
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
    return (new QueryEngine($this->related::getTable(), $this->related::class))
      ->where($this->foreignKey, '=', $this->parent->id)
      ->get();
  }
}
