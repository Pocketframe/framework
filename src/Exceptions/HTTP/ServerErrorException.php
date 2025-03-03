<?php

namespace Pocketframe\Exceptions\HTTP;

use Pocketframe\Exceptions\PocketframeException;

class ServerErrorException extends PocketframeException
{
  public function __construct($message = "Internal Server Error")
  {
    parent::__construct($message, 500);
  }
}
