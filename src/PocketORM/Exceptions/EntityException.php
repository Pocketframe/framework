<?php

namespace Pocketframe\PocketORM\Exceptions;

use RuntimeException;

class EntityException extends RuntimeException
{
  // Custom error codes
  public const ENTITY_NOT_FOUND = 1000;
  public const PERSISTENCE_FAILURE = 1001;
  public const MASS_ASSIGNMENT_ERROR = 1002;
  public const RELATIONSHIP_ERROR = 1003;
  public const RECORD_CORRUPTION = 1004;

  protected string $entityClass;
  protected array $context = [];

  public function __construct(
    string $message,
    int $code = 0,
    ?string $entityClass = null,
    array $context = []
  ) {
    parent::__construct($message, $code);
    $this->entityClass = $entityClass ?? '';
    $this->context = $context;
  }

  public function getEntityClass(): string
  {
    return $this->entityClass;
  }

  public function getContext(): array
  {
    return $this->context;
  }
}
