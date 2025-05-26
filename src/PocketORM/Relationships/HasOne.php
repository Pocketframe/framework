<?php

namespace Pocketframe\PocketORM\Relationships;

use Pocketframe\PocketORM\Entity\Entity;
use Pocketframe\PocketORM\QueryEngine\QueryEngine;

class HasOne
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
    $records   = $this->chunkedWhereIn($query->select($columns), $this->foreignKey, $parentIds);

    $grouped = [];
    foreach ($records as $rec) {
      $fk = $rec->{$this->foreignKey} ?? null;
      if ($fk !== null && !isset($grouped[$fk])) {
        $grouped[$fk] = $rec;
      }
    }
    return $grouped;
  }

  public function deepFetchUsingEngine(array $parents, QueryEngine $engine): array
  {
    $parentIds = array_map(fn(Entity $p) => $p->{$this->parentKey}, $parents);
    $records   = $this->chunkedWhereIn($engine, $this->foreignKey, $parentIds);

    $grouped = [];
    foreach ($records as $rec) {
      $fk = $rec->{$this->foreignKey} ?? null;
      if ($fk !== null && !isset($grouped[$fk])) {
        $grouped[$fk] = $rec;
      }
    }
    return $grouped;
  }

  public function get(): ?object
  {
    return (new QueryEngine($this->related))
      ->where($this->foreignKey, '=', $this->parent->{$this->parentKey})
      ->first();
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
