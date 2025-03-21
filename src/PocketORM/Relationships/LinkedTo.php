<?php

namespace Pocketframe\PocketORM\Relationships;

use Pocketframe\PocketORM\Database\QueryEngine;

// same as belongs to
class LinkedTo
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

  public function resolve()
  {
    return (new QueryEngine($this->related::getTable()))
      ->where('id', '=', $this->parent->{$this->foreignKey})
      ->first();
  }
}
