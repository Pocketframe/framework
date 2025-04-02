<?php

declare(strict_types=1);

namespace Pocketframe\Console;

class Kernel
{
  protected array $commands = [];

  public function __construct(array $argv)
  {
    $this->registerCommands($argv);
  }

  private function registerCommands(array $argv): void
  {
    // Command registry: map command names to their class and description.
    $this->commands = [
      'serve'  => [
        'class' => \Pocketframe\Console\Commands\ServeCommand::class,
        'desc'  => 'Start the built-in PHP server (automatically assigns a free port).'
      ],
      'controller:create'  => [
        'class' => \Pocketframe\Console\Commands\CreateControllerCommand::class,
        'desc'  => 'Generate a new controller file. Options: --api, --resource (-r), --invokable (-i).'
      ],
      'entity:create' => [
        'class' => \Pocketframe\Console\Commands\CreateEntityCommand::class,
        'desc' => 'Create a new entity (use -s for migration, -b for blueprint)'
      ],
      'schema:create' => [
        'class' => \Pocketframe\Console\Commands\CreateTableScriptCommand::class,
        'desc' => 'Create a new table script'
      ],
      'schema' => [
        'class' => \Pocketframe\Console\Commands\SchemaCommand::class,
        'desc'  => 'Manage database schemas (apply, rollback, fresh)'
      ],
      'planter:create' => [
        'class' => \Pocketframe\Console\Commands\CreateDataPlanterCommand::class,
        'desc' => 'Create a new data planter'
      ],
      'plant' => [
        'class' => \Pocketframe\Console\Commands\PlantCommand::class,
        'desc'  => 'Plant the database with data planters (use --class=PlanterClass for specific planter)'
      ],
      'blueprint:create' => [
        'class' => \Pocketframe\Console\Commands\CreateBlueprintCommand::class,
        'desc' => 'Create a new Entity blueprint'
      ],
      'middleware:create'  => [
        'class' => \Pocketframe\Console\Commands\CreateMiddlewareCommand::class,
        'desc'  => 'Generate a new middleware class.'
      ],
      'clear:views'  => [
        'class' => \Pocketframe\Console\Commands\ClearViewsCommand::class,
        'desc'  => 'Clear compiled/cached views located in store/framework/views.'
      ],
      'add:key' => [
        'class' => \Pocketframe\Console\Commands\AddKeyCommand::class,
        'desc'  => 'Generate a new application key.'
      ],
      'view:create' => [
        'class' => \Pocketframe\Console\Commands\CreateViewCommand::class,
        'desc'  => 'Generate a new view file.'
      ],
      'component:create' => [
        'class' => \Pocketframe\Console\Commands\CreateComponentCommand::class,
        'desc'  => 'Generate a new component class with its view stub (optionally with inline view).'
      ],
      'partial:create' => [
        'class' => \Pocketframe\Console\Commands\CreatePartialCommand::class,
        'desc'  => 'Generate a new partial view file.'
      ],
      'store:link' => [
        'class' => \Pocketframe\Console\Commands\CreateStoreLinkCommand::class,
        'desc'  => 'Create a symbolic link to the store directory.'
      ],
      'help' => [
        'class' => \Pocketframe\Console\Commands\HelpCommand::class,
        'desc'  => 'Display a list of all available commands.'
      ],
      'about' => [
        'class' => \Pocketframe\Console\Commands\AboutCommand::class,
        'desc'  => 'Display information about the Pocketframe framework.'
      ],
    ];

    // Check if the user requested help with the --help flag.
    $this->checkForHelp($argv);

    // processs the command
    $this->processCommand($argv);
  }

  public function checkForHelp(array $argv): void
  {
    // Check if the user requested help with the --help flag.
    if (isset($argv[1]) && $argv[1] === '--help') {
      if (isset($argv[2])) {
        $commandToHelp = $argv[2];
        if (isset($this->commands[$commandToHelp])) {
          // Display help for the specific command.
          $desc = $this->commands[$commandToHelp]['desc'] ?? 'No description available.';
          echo "Help for command '{$commandToHelp}':\n";
          echo $desc . "\n";
        } else {
          echo "Unknown command '{$commandToHelp}' for help.\n";
        }
      } else {
        $commandName = 'help';
        $args = [];
        $commandClass = $this->commands[$commandName]['class'];
        $command = new $commandClass($args);
        $command->handle();
      }
      exit;
    }
  }

  public function processCommand(array $argv): void
  {
    // Normal command processing.
    $commandName = $argv[1] ?? null;

    if (!$commandName) {
      $commandName = 'help';
      $args = [];
    } elseif (!isset($this->commands[$commandName])) {
      echo "Unknown command: {$commandName}\n\n";
      $commandName = 'help';
      $args = [];
    } else {
      // Get only the arguments after the command name.
      $args = array_slice($argv, 2);
    }

    $commandClass = $this->commands[$commandName]['class'];
    $command = new $commandClass($args);
    $command->handle();
  }
}
