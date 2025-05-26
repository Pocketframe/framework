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
  private string $parentKey;
  private string $foreignKey;

  public function __construct(Entity $parent, string $related, string $parentKey = 'id', ?string $foreignKey = null)
  {
    $this->parent     = $parent;
    $this->related    = $related;
    $this->parentKey  = $parentKey;

    $className = get_class($parent);
    $baseName = (new \ReflectionClass($className))->getShortName();
    $this->foreignKey = $foreignKey ?? strtolower($baseName) . '_id';
  }

  public function deepFetch(array $parents, array $columns = ['*']): array
  {
    $parentIds = array_map(fn(Entity $p) => $p->{$this->parentKey}, $parents);
    $query     = new QueryEngine($this->related);
    return self::groupByKey(
      $this->chunkedWhereIn($query->select($columns), $this->foreignKey, $parentIds),
      $this->foreignKey
    );
  }

  public function deepFetchUsingEngine(array $parents, QueryEngine $engine): array
  {
    $parentIds = array_map(fn(Entity $p) => $p->{$this->parentKey}, $parents);
    $records   = $this->chunkedWhereIn($engine, $this->foreignKey, $parentIds);
    return self::groupByKey($records, $this->foreignKey);
  }

  public function get(): DataSet
  {
    return (new QueryEngine($this->related))
      ->where($this->foreignKey, '=', $this->parent->{$this->parentKey})
      ->get();
  }

  public function getForeignKey(): string
  {
    return $this->foreignKey;
  }

  public function getParentKey(): string
  {
    return $this->parentKey;
  }
}
