<?php

declare(strict_types=1);

namespace Pocketframe\Exceptions;

use PDOException;
use RuntimeException;

class DatabaseException extends PDOException implements \Throwable
{
  public function __construct($message = "Failed to connect to the database")
  {
    parent::__construct(
      $message
    );
  }
}
