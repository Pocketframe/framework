<?php

namespace Pocketframe\Exceptions\HTTP;

use Pocketframe\Exceptions\ExceptionHandlerInterface;
use Throwable;

abstract class HttpException extends \RuntimeException
{
  protected int $statusCode;
  protected string $defaultMessage;

  public function __construct(
    ?string $message = null,
    int $code = 0,
    ?Throwable $previous = null
  ) {
    parent::__construct(
      $message ?? $this->defaultMessage,
      $code,
      $previous
    );
  }

  public function getStatusCode(): int
  {
    return $this->statusCode;
  }
}
