<?php

namespace Pocketframe\PocketORM\Exceptions;

final class EntityNotFoundError extends EntityException
{
  public function __construct(string $entityClass, array $criteria)
  {
    parent::__construct(
      "No {$entityClass} record found matching criteria",
      self::ENTITY_NOT_FOUND,
      $entityClass,
      ['criteria' => $criteria]
    );
  }

  public function getSearchCriteria(): array
  {
    return $this->context['criteria'] ?? [];
  }
}
