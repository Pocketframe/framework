<?php

namespace Pocketframe\PocketORM\Exceptions;

final class ModelNotFoundError extends ModelException
{
  public function __construct(string $modelClass, array $criteria)
  {
    parent::__construct(
      "No {$modelClass} record found matching criteria",
      self::MODEL_NOT_FOUND,
      $modelClass,
      ['criteria' => $criteria]
    );
  }

  public function getSearchCriteria(): array
  {
    return $this->context['criteria'] ?? [];
  }
}
