<?php

declare(strict_types=1);

namespace Pocketframe\Exceptions;

use Exception;
use Pocketframe\Contracts\PocketframeExceptionInterface;

class PocketframeException extends Exception implements PocketframeExceptionInterface
{
  public string $errorType;
  public function __construct(string $message, int $code = 500, string $errorType = 'error', ?Exception $previous = null)
  {
    parent::__construct($message, $code, $previous);
    $this->errorType = $errorType;
  }

  public function getErrorType(): string
  {
    return $this->errorType;
  }
}
