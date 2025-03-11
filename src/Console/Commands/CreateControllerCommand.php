<?php

namespace Pocketframe\Console\Commands;

use Pocketframe\Contracts\CommandInterface;

/**
 * Class CreateControllerCommand
 *
 * Generates a new controller file based on stubs.
 *
 * Usage:
 *  php pocket controller:create ControllerName [--api] [--resource|--invokable]
 *
 * When --api is provided, the generated controller will be placed in the Api folder and will load the
 * 'controller.api.stub' file. Otherwise, the controller is created under Web.
 *
 * @package Pocketframe\Console\Commands
 */
class CreateControllerCommand implements CommandInterface
{
  /**
   * @var array Command-line arguments passed to the command.
   */
  protected array $args;

  /**
   * @var string Path to the stubs directory.
   */
  protected string $stubPath;

  /**
   * CreateControllerCommand constructor.
   *
   * @param array $args Command-line arguments.
   */
  public function __construct(array $args)
  {
    $this->args = $this->mapShortOptions($args);
    $this->stubPath = base_path('vendor/pocketframe/framework/src/stubs/controllers');
  }

  private function mapShortOptions(array $args): array
  {
    return array_map(function ($arg) {
      return match ($arg) {
        '-r'    => '--resource',
        '-i'    => '--invokable',
        default => $arg,
      };
    }, $args);
  }

  /**
   * Handle the command execution.
   *
   * @return void
   */
  public function handle(): void
  {
    // Ensure a controller name is provided
    $controllerInput = $this->args[0] ?? null;
    if (!$controllerInput) {
      echo "Usage: php pocket controller:create ControllerName [--api] [--resource [-r]|--invokable [-i]]\n";
      exit(1);
    }

    // Extract the actual controller name and any subdirectory from input like "DemoDir/TestController"
    $controllerParts = explode('/', trim($controllerInput, '/'));
    $controllerName = ucfirst(array_pop($controllerParts)); // e.g. TestController
    $subDirectory = implode('/', $controllerParts); // e.g. "DemoDir" (empty if none)

    // Validate controller name (must start with a letter and contain only letters, numbers, or underscores)
    if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $controllerName)) {
      echo "Error: Invalid controller name '{$controllerName}'.\n";
      exit(1);
    }

    // Determine if it's an API or Web controller.
    // If --api flag is present, set folder to 'Api' and force stub type to 'api'.
    if (in_array('--api', $this->args, true)) {
      $folder = 'Api';
      $type = 'api';
    } else {
      $folder = 'Web';
      // Determine stub type for Web controllers
      if (in_array('--resource', $this->args, true)) {
        $type = 'resource';
      } elseif (in_array('--invokable', $this->args, true)) {
        $type = 'invokable';
      } else {
        $type = 'empty';
      }
    }

    $namespace = "App\\Controllers\\{$folder}" . ($subDirectory ? '\\' . str_replace('/', '\\', $subDirectory) : '');
    $controllerPath = base_path("app/Controllers/{$folder}/" . ($subDirectory ? "{$subDirectory}/" : '') . "{$controllerName}.php");

    // Prevent overwriting existing controllers
    if (file_exists($controllerPath)) {
      echo "🥳 Controller already exists: {$controllerPath}\n";
      exit(1);
    }

    // Ensure the target directory exists.
    if (!is_dir(dirname($controllerPath))) {
      mkdir(dirname($controllerPath), 0777, true);
    }

    // Load the appropriate stub file based on type.
    $stubFile = "{$this->stubPath}/controller.{$type}.stub";
    if (!file_exists($stubFile)) {
      echo "🤔 Stub file not found: {$stubFile}\n";
      exit(1);
    }

    // Read and replace placeholders in the stub file.
    $template = file_get_contents($stubFile);
    $template = str_replace(
      ['{{namespace}}', '{{controllerName}}'],
      [rtrim($namespace, '\\'), $controllerName],
      $template
    );

    // Create the controller file.
    file_put_contents($controllerPath, $template);
    echo "💪 Controller created: {$controllerPath}\n";
  }
}
