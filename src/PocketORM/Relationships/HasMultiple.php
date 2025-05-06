<?php

namespace Pocketframe\PocketORM\Relationships;

use Pocketframe\PocketORM\Essentials\DataSet;
use Pocketframe\PocketORM\Entity\Entity;
use Pocketframe\PocketORM\QueryEngine\QueryEngine;

/**
 * HasMultiple: one-to-many relationship (a parent has multiple children).
 */
class HasMultiple
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

  public function deepFetch(array $parents): array
  {
    $parentIds = array_column($parents, 'id');

    $query = new QueryEngine($this->related);

    $relatedRecords = $this->chunkedWhereIn($query, $this->foreignKey, $parentIds);

    return self::groupByKey($relatedRecords, $this->foreignKey);
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
