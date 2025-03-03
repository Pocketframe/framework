<?php

namespace Pocketframe\Exceptions\Database;

use Pocketframe\Exceptions\PocketframeException;

class QueryException extends PocketframeException
{
  public function __construct($message = "Database query error")
  {
    parent::__construct($message, 500);
  }
}
