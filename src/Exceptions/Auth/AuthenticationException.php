<?php

namespace Pocketframe\Exceptions\Auth;

use Pocketframe\Exceptions\PocketframeException;

class AuthenticationException extends PocketframeException
{
  public function __construct($message = "Authentication failed")
  {
    parent::__construct($message, 401);
  }
}
