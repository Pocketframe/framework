<?php

declare(strict_types=1);

namespace Pocketframe\Console;

class Kernel
{
  protected array $commands = [];

  public function __construct()
  {
    $this->registerCommands();
  }

  private function registerCommands(): void
  {
    $this->commands = [
      'serve' => \Pocketframe\Console\Commands\ServeCommand::class,
    ];
  }

  public function run(array $arguments): void
  {
    $command = $arguments[1] ?? 'help';

    if (!isset($this->commands[$command])) {
      echo "Command '{$command}' not found.\n";
      exit(1);
    }

    $commandClass = new $this->commands[$command]();
    $commandClass->handle();
  }
}
