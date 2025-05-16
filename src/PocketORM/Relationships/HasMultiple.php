<?php

namespace Pocketframe\PocketORM\Relationships;

use Pocketframe\PocketORM\Essentials\DataSet;
use Pocketframe\PocketORM\Entity\Entity;
use Pocketframe\PocketORM\QueryEngine\QueryEngine;

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

  public function deepFetch(array $parents, array $columns = ['*']): array
  {
    $parentIds = array_map(fn(Entity $p) => $p->id, $parents);
    $query     = new QueryEngine($this->related);
    return self::groupByKey(
      $this->chunkedWhereIn($query->select($columns), $this->foreignKey, $parentIds),
      $this->foreignKey
    );
  }

  public function deepFetchUsingEngine(array $parents, QueryEngine $engine): array
  {
    $parentIds = array_map(fn(Entity $p) => $p->id, $parents);
    $records   = $this->chunkedWhereIn($engine, $this->foreignKey, $parentIds);
    return self::groupByKey($records, $this->foreignKey);
  }

  public function get(): DataSet
  {
    return (new QueryEngine($this->related))
      ->where($this->foreignKey, '=', $this->parent->id)
      ->get();
  }

  public function getForeignKey(): string
  {
    return $this->foreignKey;
  }

  public function getParentKey(): string
  {
    return 'id';
  }
}
