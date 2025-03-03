<?php

namespace Pocketframe\Exceptions\Cache;

use Pocketframe\Exceptions\PocketframeException;

class CacheException extends PocketframeException
{
  public function __construct($message = "Cache error")
  {
    parent::__construct($message, 500);
  }
}
