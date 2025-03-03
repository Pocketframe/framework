<?php

namespace Pocketframe\Exceptions\HTTP;

use Pocketframe\Exceptions\PocketframeException;

class NotFoundException extends PocketframeException
{
  public function __construct($message = 'Page not found')
  {
    parent::__construct($message, 404);
  }
}
