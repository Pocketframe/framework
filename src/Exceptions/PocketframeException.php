<?php

declare(strict_types=1);

namespace Pocketframe\Exceptions;

use Exception;
use Pocketframe\Contracts\PocketframeExceptionInterface;

class PocketframeException extends Exception
{
  protected array $context;

  public function __construct(string $message = "An error occurred", int $code = 500, array $context = [])
  {
    parent::__construct($message, $code);
    $this->context = $context;
  }

  /**
   * Get additional context data for debugging.
   */
  public function getContext(): array
  {
    return $this->context;
  }
}
