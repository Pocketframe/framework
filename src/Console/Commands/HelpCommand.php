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
    // ANSI color codes for styling the output.
    $blue   = "\033[34m";   // Dark Blue
    $yellow = "\033[33m";   // Yellow for headers
    $green  = "\033[32m";   // Green for labels
    $reset  = "\033[0m";

    // Retrieve the version dynamically (for example, via an environment variable).
    // Ensure that your environment is loaded before running commands.
    $version = getenv('POCKETFRAME_VERSION')
    ?: (($gitVersion = shell_exec('git describe --tags --abbrev=0 2>/dev/null')) ? trim($gitVersion) : null)
    ?: (file_exists(BASE_PATH . '/VERSION') ? trim(file_get_contents(BASE_PATH . '/VERSION')) : 'Unknown');
    // Documentation URL.
    $documentationUrl = "https://pocketframe.github.io/docs/";

    // Display the framework information similar to Laravel's about command.
    echo "\n\n";
    echo "{$yellow}Pocketframe Framework{$reset}\n\n";
    echo "{$blue}About Application{$reset}\n\n";

    echo "{$green}Version:         {$reset} {$version}\n";
    echo "\n";
    echo "{$green}Documentation:   {$reset} {$documentationUrl}\n";
    echo "\n";
    echo "{$green}PHP Version:     {$reset} " . PHP_VERSION . "\n";
    echo "{$green}Operating System:{$reset} " . php_uname('s') . " " . php_uname('r') . "\n";

    // Optionally include environment info (e.g. APP_ENV if defined)
    $env = getenv('APP_ENV') ?: 'production';
    echo "{$green}Environment:     {$reset} {$env}\n\n";

    // Available commands
    echo "{$yellow}Available Commands:{$reset}\n";
    echo "{$blue}  serve               {$reset}Start the built-in PHP server (automatically assigns a free port).\n";
    echo "{$blue}  controller:create   {$reset}Generate a new controller file. Options: --api, --resource (-r), --invokable (-i).\n";
    echo "{$blue}  entity:create       {$reset}Create a new entity class.\n";
    echo "{$blue}  schema:create       {$reset}Create a new schema script. Options: --schema (-s) --blueprint (-b)\n";
    echo "{$blue}  schema:session-table   {$reset}Create a new session table.\n";
    echo "{$blue}  planter:create      {$reset}Create a new data planter.\n";
    echo "{$blue}  blueprint:create    {$reset}Create a new Entity blueprint.\n";
    echo "{$blue}  middleware:create   {$reset}Generate a new middleware class.\n";
    echo "{$blue}  clear:views         {$reset}Clear compiled/cached views located in store/framework/views.\n";
    echo "{$blue}  add:key             {$reset}Generate a new application key.\n";
    echo "{$blue}  view:create         {$reset}Generate a new view file.\n";
    echo "{$blue}  component:create    {$reset}Generate a new component class with its view stub (optionally with inline view).\n";
    echo "{$blue}  partial:create      {$reset}Generate a new partial view file.\n";
    echo "{$blue}  store:link          {$reset}Create a symbolic link to the store directory.\n";
    echo "{$blue}  help                {$reset}Display a list of all available commands.\n";

    echo "\n{$yellow}Usage: php pocket <command> [options]{$reset}\n";
  }
}
