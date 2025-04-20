<?php

namespace Pocketframe\Container;

use Pocketframe\Console\Kernel;
use Pocketframe\Container\Container;
use Pocketframe\Contracts\ExceptionHandlerInterface;
use Pocketframe\Database\Database;
use Pocketframe\Exceptions\ExceptionHandler;
use Pocketframe\Exceptions\Handler;
use Pocketframe\Logger\Logger;
use Pocketframe\Package\PackageLoader;
use Pocketframe\PocketORM\Database\Connection;
use Pocketframe\Sessions\Mask\Session;

class ContainerRegister
{

  public function register(Container $container)
  {
    $container->bind('viewPath', fn() => __DIR__ . '/../resources/views/errors');

    // Bind the Handler class with its dependencies
    $container->bind(Handler::class, function () use ($container) {
      return new Handler(
        $container->get(Logger::class),
        base_path()
      );
    });

    $container->singleton(
      \Pocketframe\Contracts\ExceptionHandlerInterface::class,
      \Pocketframe\Exceptions\Handler::class
    );

    // Create the handler instance
    $handler = $container->get(\Pocketframe\Exceptions\Handler::class);

    // Register the exception handler
    set_exception_handler([$handler, 'handle']);

    // Bind the Database class with its dependencies
    $container->bind(Database::class, function () {
      Connection::configure();
      return new Database(Connection::getInstance());
    });

    // Bind the Logger class
    $container->bind(Logger::class, function () {
      return new Logger();
    });

    // Bind the session manager
    $container->singleton(Session::class, fn() => new Session());

    // Bind Kernel without needing $argv.
    $container->singleton(Kernel::class, fn() => new Kernel());

    // Set the global container instance
    Container::getInstance()->bind(Container::class, fn() => $container);

    // auto-discover and register packages
    PackageLoader::loadPackages($container);
  }
}
