<?php

namespace Pocketframe\PocketORM\Exceptions;

final class RelationshipResolutionError extends ModelException
{
  public function __construct(string $modelClass, string $relation)
  {
    parent::__construct(
      "Failed to resolve relationship '{$relation}' on {$modelClass}",
      self::RELATIONSHIP_ERROR,
      $modelClass,
      ['relationship' => $relation]
    );
  }
}
