<?php

namespace Pocketframe\PocketORM\Relationships;

use Pocketframe\PocketORM\Database\QueryEngine;
use Pocketframe\PocketORM\Entity\Entity;
use Pocketframe\PocketORM\Essentials\DataSet;

/**
 * OwnedBy: belongs-to relationship (the parent "owns" a foreign key referencing another entity's primary key).
 */
class OwnedBy
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
    // Extract the foreign key values
    $foreignKeys = array_unique(
      array_column(array_map(fn($p) => (array)$p, $parents), $this->foreignKey)
    );

    return (new QueryEngine($this->related))
      ->whereIn('id', $foreignKeys)
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

  public function resolve(): ?object
  {
    // Return the single related record
    return (new QueryEngine($this->related))
      ->where('id', '=', $this->parent->{$this->foreignKey})
      ->first();
  }
}
