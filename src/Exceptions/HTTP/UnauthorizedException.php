<?php

namespace Pocketframe\Exceptions\HTTP;

use Pocketframe\Exceptions\PocketframeException;

class UnauthorizedException extends PocketframeException
{
  public function __construct($message = "Unauthorized access")
  {
    parent::__construct($message, 401);
  }
}
