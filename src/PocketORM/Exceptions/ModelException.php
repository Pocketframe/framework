<?php

namespace Pocketframe\PocketORM\Exceptions;

use RuntimeException;

class ModelException extends RuntimeException
{
  // Custom error codes
  public const MODEL_NOT_FOUND = 1000;
  public const PERSISTENCE_FAILURE = 1001;
  public const MASS_ASSIGNMENT_ERROR = 1002;
  public const RELATIONSHIP_ERROR = 1003;
  public const RECORD_CORRUPTION = 1004;

  protected string $modelClass;
  protected array $context = [];

  public function __construct(
    string $message,
    int $code = 0,
    ?string $modelClass = null,
    array $context = []
  ) {
    parent::__construct($message, $code);
    $this->modelClass = $modelClass ?? '';
    $this->context = $context;
  }

  public function getModelClass(): string
  {
    return $this->modelClass;
  }

  public function getContext(): array
  {
    return $this->context;
  }
}
