<?php

declare(strict_types=1);

namespace Pocketframe\Package\Contract;

use Pocketframe\Container\Container;

interface PackageInterface
{
  /**
   * Register the package's bindings, commands, or other services.
   *
   * @param Container $container The application container.
   * @return void
   */
  public function register(Container $container): void;
}
