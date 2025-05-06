<?php

namespace Pocketframe\PocketORM\Exceptions;

final class MassAssignmentError extends EntityException
{
  public function __construct(string $modelClass, string $invalidKey)
  {
    parent::__construct(
      "Mass assignment blocked for '{$invalidKey}' on {$modelClass}",
      self::MASS_ASSIGNMENT_ERROR,
      $modelClass,
      ['invalid_key' => $invalidKey]
    );
  }
}
