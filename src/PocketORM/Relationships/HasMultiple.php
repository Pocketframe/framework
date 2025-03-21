<?php

namespace Pocketframe\PocketORM\Relationships;

use Pocketframe\PocketORM\Database\QueryEngine;
use Pocketframe\PocketORM\Essentials\RecordSet;

// same as has many
class HasMultiple
{
  private $parent;
  private $related;
  private $foreignKey;

  public function __construct($parent, string $related, string $foreignKey)
  {
    $this->parent = $parent;
    $this->related = $related;
    $this->foreignKey = $foreignKey;
  }

  public function get(): RecordSet
  {
    return (new QueryEngine($this->related::getTable()))
      ->where($this->foreignKey, '=', $this->parent->id)
      ->get();
  }
}
