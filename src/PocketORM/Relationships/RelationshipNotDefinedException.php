<?php

namespace Pocketframe\PocketORM\Relationships;

use Exception;

class RelationshipNotDefinedException extends Exception
{
  public function __construct($relation, $entity)
  {
    parent::__construct("Relationship '$relation' is not defined on entity '$entity'. Did you forget to define it in relationships() property?");
  }
}
