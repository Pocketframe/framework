<?php

namespace Pocketframe\Exceptions\Config;

use Pocketframe\Exceptions\PocketframeException;

class ConfigurationException extends PocketframeException
{
  public function __construct($message = "Invalid configuration")
  {
    parent::__construct($message, 500);
  }
}
