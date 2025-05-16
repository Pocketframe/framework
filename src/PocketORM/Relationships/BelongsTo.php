<?php

namespace Pocketframe\PocketORM\Relationships;

use Pocketframe\PocketORM\Entity\Entity;
use Pocketframe\PocketORM\QueryEngine\QueryEngine;

class BelongsTo
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
    $fks   = array_map(fn(Entity $p) => $p->{$this->foreignKey}, $parents);
    $query = new QueryEngine($this->related);
    $recs  = $this->chunkedWhereIn($query->select($columns), $this->getParentKey(), $fks);

    $grouped = [];
    foreach ($recs as $rec) {
      $id = $rec->{$this->getParentKey()} ?? null;
      if ($id !== null) {
        $grouped[$id] = $rec;
      }
    }
    return $grouped;
  }

  public function deepFetchUsingEngine(array $parents, QueryEngine $engine): array
  {
    $fks    = array_map(fn(Entity $p) => $p->{$this->foreignKey}, $parents);
    $recs   = $this->chunkedWhereIn($engine, $this->getParentKey(), $fks);
    $grouped = [];
    foreach ($recs as $rec) {
      $id = $rec->{$this->getParentKey()} ?? null;
      if ($id !== null) {
        $grouped[$id] = $rec;
      }
    }
    return $grouped;
  }

  public function resolve(): ?object
  {
    $fk = $this->parent->{$this->foreignKey};
    if ($fk === null) {
      return null;
    }
    return (new QueryEngine($this->related))
      ->where($this->getParentKey(), '=', $fk)
      ->first();
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
