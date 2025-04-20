<?php

namespace Pocketframe\Exceptions\HTTP;


class NotFoundException extends HttpException
{
  protected int $statusCode = 404;
  protected string $defaultMessage = 'The requested resource was not found';
}
