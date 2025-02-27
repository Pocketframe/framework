<?php

namespace Pocketframe\Container;

use Pocketframe\Container\Container;
use Pocketframe\Exceptions\Handler;
use Pocketframe\Database\Database;
use Pocketframe\Logger\Logger;

class ContainerRegister
{

  public function register(Container $container)
  {
    $container->bind(Handler::class, function () {
      return new Handler();
    });

    $container->bind(Database::class, function () {
      $config = require config_path('database');
      return new Database($config['database']);
    });

    $container->bind(Logger::class, function () {
      return new Logger();
    });
  }
}
