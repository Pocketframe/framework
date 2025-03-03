<?php

namespace Pocketframe\Exceptions\HTTP;

use Pocketframe\Exceptions\PocketframeException;

class ForbiddenException extends PocketframeException
{
  public function __construct($message = "Forbidden")
  {
    parent::__construct($message, 403);
  }
}
