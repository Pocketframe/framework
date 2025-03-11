<?php

namespace Pocketframe\Console\Commands;

use Pocketframe\Contracts\CommandInterface;

/**
 * Class HelpCommand
 *
 * Displays the list of available commands.
 *
 * Usage:
 *   php pocket help
 *
 * @package Pocketframe\Console\Commands
 */
class HelpCommand implements CommandInterface
{
  public function __construct(array $args)
  {
    // No arguments needed
  }

  public function handle(): void
  {
    // ANSI color codes for better visibility
    $green = "\033[32m";
    $yellow = "\033[33m";
    $reset = "\033[0m";

    echo "{$yellow}Available Commands:{$reset}\n";
    echo "{$green}  serve                 {$reset}Start the built-in PHP server (auto-assigns a free port).\n";
    echo "{$green}  controller:create     {$reset}Generate a new controller class. Available options: --api, --resource (-r), --invokable (-i).\n";
    echo "{$green}  middleware:create     {$reset}Generate a new middleware class.\n";
    echo "{$green}  clear:views           {$reset}Clear compiled/cached views from store/framework/views.\n";
    echo "{$green}  add:key               {$reset}Generate a new application key.\n";
    echo "{$green}  help                  {$reset}Display this help message.\n";

    echo "\n{$yellow}Usage: php pocket <command> [options]{$reset}\n";
  }
}
