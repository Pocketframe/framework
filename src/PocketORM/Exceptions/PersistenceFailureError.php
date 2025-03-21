<?php

namespace Pocketframe\PocketORM\Exceptions;

final class PersistenceFailureError extends ModelException
{
  public function __construct(string $modelClass, string $operation)
  {
    parent::__construct(
      "Failed to persist {$modelClass} during {$operation}",
      self::PERSISTENCE_FAILURE,
      $modelClass,
      ['operation' => $operation]
    );
  }
}
