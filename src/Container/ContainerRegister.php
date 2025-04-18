<?php

namespace Pocketframe\Container;

use Pocketframe\Console\Kernel;
use Pocketframe\Container\Container;
use Pocketframe\Exceptions\Handler;
use Pocketframe\Database\Database;
use Pocketframe\Logger\Logger;
use Pocketframe\Package\PackageLoader;

class ContainerRegister
{

  public function register(Container $container)
  {
    $container->bind('viewPath', fn() => __DIR__ . '/../resources/views/errors');

    // Bind the Handler class with its dependencies
    $container->bind(Handler::class, function () use ($container) {
      return new Handler(
        $container->get(Logger::class),
      );
    });

    $container->bind(Database::class, function () {
      $config = config('database.database');
      return new Database($config);
    });

    $container->bind(Logger::class, function () {
      return new Logger();
    });

    // Bind Kernel without needing $argv.
    $container->singleton(Kernel::class, fn() => new Kernel());

    // Set the global container instance
    Container::getInstance()->bind(Container::class, fn() => $container);

    // auto-discover and register packages
    PackageLoader::loadPackages($container);
  }
}
