<?php

namespace Pocketframe\PocketORM\Exceptions;

final class RelationshipResolutionError extends ModelException
{
  public function __construct(string $entityClass, string $relation)
  {
    parent::__construct(
      "Failed to resolve relationship '{$relation}' on {$entityClass}",
      self::RELATIONSHIP_ERROR,
      $entityClass,
      ['relationship' => $relation]
    );
  }
}
