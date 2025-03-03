<?php

namespace Pocketframe\Exceptions\FileSystem;

use Pocketframe\Exceptions\PocketframeException;

class FileSystemException extends PocketframeException
{
  public function __construct($message = "File system error")
  {
    parent::__construct($message, 500);
  }
}
