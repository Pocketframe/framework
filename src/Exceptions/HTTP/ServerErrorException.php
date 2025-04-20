<?php

namespace Pocketframe\Exceptions\HTTP;

use Pocketframe\Exceptions\PocketframeException;

class ServerErrorException extends HttpException
{
  protected int $statusCode = 500;
  protected string $defaultMessage = 'An internal server error occurred';
}
