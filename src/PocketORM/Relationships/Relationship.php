<?php

namespace Pocketframe\PocketORM\Relationships;

use Pocketframe\Database\Model;
use Pocketframe\PocketORM\Database\QueryEngine;

abstract class Relationship
{
  protected Model $parent;
  protected string $related;
  protected string $foreignKey;
  protected string $localKey;

  public function __construct(Model $parent, string $related, string $foreignKey, string $localKey = 'id')
  {
    $this->parent = $parent;
    $this->related = $related;
    $this->foreignKey = $foreignKey;
    $this->localKey = $localKey;
  }

  abstract public function getResults();
  abstract public function applyConstraints(QueryEngine $query);
}
