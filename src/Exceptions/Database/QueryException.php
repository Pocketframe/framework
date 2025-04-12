<?php

namespace Pocketframe\Exceptions\Database;

use PDOException;
use RuntimeException;

class QueryException extends RuntimeException implements \Throwable
{
  public function __construct($message = "Failed to execute query.")
  {
    parent::__construct(
      $message
    );
  }
}
