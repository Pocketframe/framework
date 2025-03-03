<?php

namespace Pocketframe\Exceptions\Middleware;

use Pocketframe\Exceptions\PocketframeException;

class MiddlewareException extends PocketframeException
{
  public function __construct($message = "Middleware error")
  {
    parent::__construct($message, 500);
  }
}
