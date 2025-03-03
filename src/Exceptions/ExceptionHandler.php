<?php

namespace Pocketframe\Exceptions;

use Pocketframe\Container\Container;

class ExceptionHandler
{
  public static function handle(\Throwable $exception)
  {
    // Get the global container instance (or create one if it doesn't exist)
    $container = Container::getInstance();

    // Resolve the Handler from the container
    $handler = $container->get(Handler::class);

    // Handle the exception
    $handler->handle($exception);
  }
}
