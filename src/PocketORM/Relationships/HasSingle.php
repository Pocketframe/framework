<?php

namespace Pocketframe\PocketORM\Relationships;

use Pocketframe\PocketORM\Database\QueryEngine;

// same as has one
class HasSingle
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

  public function get()
  {
    return (new QueryEngine($this->related::getTable()))
      ->where($this->foreignKey, '=', $this->parent->id)
      ->first();
  }
}
