<?php

namespace Pocketframe\PocketORM\Exceptions;

final class PersistenceFailureError extends EntityException
{
  public function __construct(string $entityClass, string $operation)
  {
    parent::__construct(
      "Failed to persist {$entityClass} during {$operation}",
      self::PERSISTENCE_FAILURE,
      $entityClass,
      ['operation' => $operation]
    );
  }
}
